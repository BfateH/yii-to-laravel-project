<?php

namespace App\Console\Commands;

use App\Modules\Currency\Application\ExchangeRateService;
use App\Modules\Currency\Domain\ExchangeRateException;
use DateTime;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:update-rates
                            {date? : Дата для обновления курсов (Y-m-d). По умолчанию - вчерашний день.}
                            {--force : Принудительно выполнить обновление, игнорируя возможные ограничения.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновляет курсы валют от настроенного провайдера.';

    /**
     * Execute the console command.
     *
     * @param ExchangeRateService $exchangeRateService
     * @return int
     */
    public function handle(ExchangeRateService $exchangeRateService): int
    {
        $inputDate = $this->argument('date');
        $isForce = $this->option('force');

        if ($inputDate) {
            try {
                $date = new DateTime($inputDate);
            } catch (\Exception $e) {
                $this->error("Неверный формат даты: {$inputDate}. Используйте Y-m-d.");
                Log::warning("Команда UpdateExchangeRatesCommand: Неверный формат даты на входе.", ['input_date' => $inputDate]);
                return self::FAILURE;
            }
        } else {
            // По умолчанию - вчерашний день, так как ЦБ РФ обычно публикует курсы на следующий день
            $date = new DateTime('yesterday');
        }

        $formattedDate = $date->format('Y-m-d');
        $this->info("Начало обновления курсов валют на дату: {$formattedDate}");


        try {
            // --- Логика обновления ---
            // Вся логика ретраев реализована внутри, например, CBRProvider::getRates
            // с использованием retry() хелпера.
            // Дедупликация на уровне планировщика (withoutOverlapping в routes/console.php)
            // предотвращает одновременный запуск этой команды.

            Log::info("Команда UpdateExchangeRatesCommand: Запуск обновления курсов.", [
                'date' => $formattedDate,
                'force' => $isForce
            ]);

            $exchangeRateService->updateRates($date);

            $this->info("Курсы валют на дату {$formattedDate} успешно обновлены.");
            Log::info("Команда UpdateExchangeRatesCommand: Курсы валют успешно обновлены.", ['date' => $formattedDate]);
            return self::SUCCESS;

        } catch (ExchangeRateException $e) {
            // Эта ошибка выбрасывается сервисом, если, например, провайдер
            // не смог получить данные даже после всех ретраев.
            $this->error("Ошибка при обновлении курсов: " . $e->getMessage());
            Log::error("Команда UpdateExchangeRatesCommand: Ошибка обновления курсов (ExchangeRateException).", [
                'date' => $formattedDate,
                'exception' => $e
            ]);
            return self::FAILURE;

        } catch (\Exception $e) {
            // Любая другая неожиданная ошибка
            $this->error("Неожиданная ошибка при обновлении курсов: " . $e->getMessage());
            Log::error("Команда UpdateExchangeRatesCommand: Неожиданная ошибка.", [
                'date' => $formattedDate,
                'exception' => $e
            ]);
            return self::FAILURE;
        }
    }
}
