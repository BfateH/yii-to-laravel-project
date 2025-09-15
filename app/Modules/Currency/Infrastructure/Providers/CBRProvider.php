<?php

namespace App\Modules\Currency\Infrastructure\Providers;

use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Domain\ExchangeRatesProviderInterface;
use DateTimeInterface;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CBRProvider implements ExchangeRatesProviderInterface
{
    private const BASE_URL = 'https://www.cbr.ru/scripts/XML_daily.asp';

    public function getName(): string
    {
        return 'cbr';
    }

    public function getRates(DateTimeInterface $date): array
    {
        $formattedDate = $date->format('d/m/Y');
        $url = self::BASE_URL . '?date_req=' . $formattedDate;

        // Получаем параметры из конфигурации
        $maxAttempts = config('currency.retry_attempts', 3);
        $baseDelayMs = config('currency.retry_delay', 1000);

        try {
            return retry(
                $maxAttempts, // Количество попыток
                function () use ($url, $formattedDate, $date) { // Callback с основной логикой
                    Log::info("Запрос курсов у ЦБ РФ", ['url' => $url]);

                    $response = Http::timeout(30)->withOptions(['verify' => config('app.env') !== 'local'])->get($url);

                    if (!$response->successful()) {
                        Log::error("Ошибка запроса к ЦБ РФ: HTTP {$response->status()}", ['url' => $url]);
                        throw new RequestException($response);
                    }

                    $xmlString = $response->body();
                    $xml = simplexml_load_string($xmlString);

                    if ($xml === false) {
                        Log::error("Ошибка парсинга XML от ЦБ РФ", ['url' => $url, 'response' => $xmlString]);
                        throw new \Exception("Ошибка парсинга XML от ЦБ РФ");
                    }

                    $rates = [];
                    $baseCurrency = config('currency.base_currency', 'RUB');

                    if ($baseCurrency !== 'RUB') {
                        Log::warning("Провайдер CBRProvider предполагает, что базовая валюта - RUB. Текущая базовая валюта: {$baseCurrency}");
                    }

                    // ЦБ РФ отдает курсы в формате: <Value>34,5678</Value> за <Nominal>100</Nominal> единиц валюты
                    // Это означает, что 100 USD = 34,5678 RUB => 1 USD = 34,5678 / 100 RUB
                    // Нам нужен курс RUB/USD (сколько RUB за 1 USD), который равен 1 / (Value/Nominal) = Nominal / Value
                    foreach ($xml->Valute as $valute) {
                        $currencyCode = (string) $valute->CharCode;
                        $nominal = (int) $valute->Nominal;
                        // Заменяем запятую на точку для корректного преобразования в float
                        $valueStr = str_replace(',', '.', (string) $valute->Value);
                        $value = (float) $valueStr;

                        if ($nominal <= 0 || $value <= 0) {
                            Log::warning("Некорректные данные курса для валюты {$currencyCode}", [
                                'nominal' => $nominal,
                                'value' => $valueStr
                            ]);
                            continue; // Пропускаем эту валюту
                        }

                        // Рассчитываем курс RUB/Currency (сколько RUB за 1 единицу валюты)
                        // Это обратная величина к курсу, предоставляемому ЦБ (Currency/RUB)
                        $rateValue = $nominal / $value;

                        // Создаем объект DateTimeImmutable для даты курса
                        $rateDate = new DateTimeImmutable($date->format('Y-m-d'));

                        $rate = new ExchangeRate(
                            'RUB', // Базовая валюта
                            $currencyCode, // Целевая валюта
                            $rateValue, // Курс RUB/Currency
                            $rateDate // Дата курса
                        );
                        $rates[] = $rate;
                    }

                    Log::info("Успешно получены курсы от ЦБ РФ", ['count' => count($rates), 'date' => $formattedDate]);
                    return $rates;
                },
                function (int $attempt, \Exception $exception) use ($baseDelayMs) { // Callback для задержки
                    // Экспоненциальная задержка: baseDelay * (2 ^ (attempt - 1))
                    $delayMs = $baseDelayMs * pow(2, $attempt - 1);
                    Log::debug("Пауза перед повторной попыткой #{$attempt} ({$delayMs}ms) из-за ошибки: " . $exception->getMessage());
                    return $delayMs;
                },
                function (\Exception $exception) { // Callback для проверки, нужно ли повторять

                    $shouldRetry = (
                        $exception instanceof ConnectionException || // Ошибки соединения
                        ($exception instanceof RequestException && $exception->response?->status() >= 500) || // 5xx ошибки сервера
                        !($exception instanceof RequestException) // Любые другие ошибки, кроме RequestException (например, парсинг XML)
                    );

                    if ($shouldRetry) {
                        Log::warning("Условие для ретрая выполнено: " . $exception->getMessage());
                    } else {
                        Log::debug("Условие для ретрая НЕ выполнено: " . $exception->getMessage());
                    }

                    return $shouldRetry;
                }
            );

        } catch (\Exception $e) { // Ловим общее исключение, так как retry выбрасывает последнюю ошибку
            Log::error("Ошибка при получении курсов от ЦБ РФ после {$maxAttempts} попыток: " . $e->getMessage(), [
                'date' => $formattedDate,
                'url' => $url,
                'exception_class' => get_class($e)
            ]);
            throw $e;
        }
    }
}
