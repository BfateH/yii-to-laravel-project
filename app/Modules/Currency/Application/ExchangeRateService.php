<?php

namespace App\Modules\Currency\Application;

use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Domain\ExchangeRateException;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use App\Modules\Currency\Domain\ExchangeRatesProviderInterface;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

class ExchangeRateService
{
    public function __construct(
        private ExchangeRatesProviderInterface $provider,
        public ExchangeRateRepositoryInterface $repository,
        private LoggerInterface                $logger,
    ) {}

    /**
     * Обновляет курсы валют на заданную дату.
     *
     * @param DateTimeInterface $date Дата для обновления.
     * @throws ExchangeRateException
     */
    public function updateRates(DateTimeInterface $date): void
    {
        try {
            $this->logger->info("Начало обновления курсов от провайдера: {$this->provider->getName()} на дату: " . $date->format('Y-m-d'));
            $rates = $this->provider->getRates($date);

            foreach ($rates as $rate) {
                $this->repository->save($rate);
            }
            $this->logger->info("Курсы успешно обновлены от провайдера: {$this->provider->getName()} на дату: " . $date->format('Y-m-d'));

        } catch (\Exception $e) {
            $this->logger->error("Ошибка при обновлении курсов от провайдера: {$this->provider->getName()} на дату: " . $date->format('Y-m-d'), ['exception' => $e]);
            throw new ExchangeRateException("Не удалось обновить курсы валют: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Конвертирует сумму из одной валюты в другую на заданную дату.
     *
     * @param float $amount Сумма для конвертации.
     * @param string $fromCurrency Код исходной валюты.
     * @param string $toCurrency Код целевой валюты.
     * @param DateTimeInterface $date Дата для получения курса.
     * @return float Сконвертированная сумма.
     * @throws ExchangeRateException Если курс не найден.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency, DateTimeInterface $date): float
    {
        // Логика конвертации:
        // 1. Если from == to, вернуть amount.
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // 2. Получить курс from -> base (например, RUB)
        $baseCurrency = config('currency.base_currency', 'RUB'); // Получаем базовую валюту

        if ($fromCurrency === $baseCurrency) {
            // 3a. Если from == base, ищем курс base -> to
            $rate = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $toCurrency);
            if (!$rate) {
                throw new ExchangeRateException("Курс {$baseCurrency} -> {$toCurrency} на дату " . $date->format('Y-m-d') . " не найден.");
            }
            return $amount * $rate->getRate();
        } elseif ($toCurrency === $baseCurrency) {
            // 3b. Если to == base, ищем курс from -> base и делим
            $rate = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $fromCurrency);
            if (!$rate) {
                throw new ExchangeRateException("Курс {$baseCurrency} -> {$fromCurrency} на дату " . $date->format('Y-m-d') . " не найден.");
            }

            return $amount / $rate->getRate();
        } else {
            // 4. Иначе, ищем from -> base и base -> to, умножаем
            $rateFromToBase = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $fromCurrency);
            $rateBaseToTo = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $toCurrency);

            if (!$rateFromToBase || !$rateBaseToTo) {
                throw new ExchangeRateException("Курс для конвертации {$fromCurrency} -> {$toCurrency} через {$baseCurrency} на дату " . $date->format('Y-m-d') . " не найден.");
            }

            return ($amount / $rateFromToBase->getRate()) * $rateBaseToTo->getRate();
        }
    }

    /**
     * Получает исторические курсы для пары валют.
     *
     * @param string $baseCurrency Код базовой валюты.
     * @param string $targetCurrency Код целевой валюты.
     * @param DateTimeInterface $startDate Начальная дата.
     * @param DateTimeInterface $endDate Конечная дата.
     * @return ExchangeRate[] Массив курсов.
     */
    public function getHistoricalRates(string $baseCurrency, string $targetCurrency, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->repository->findHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);
    }
}
