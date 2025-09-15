<?php

namespace Tests\Unit\Modules\Currency\Infrastructure\Repositories;

use App\Models\ExchangeRateModel;
use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Infrastructure\Repositories\DatabaseExchangeRateRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseExchangeRateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseExchangeRateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DatabaseExchangeRateRepository();
    }

    public function test_save_creates_new_rate()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $rateValue = 75.5;
        $date = new DateTimeImmutable('2023-10-27');

        $rate = new ExchangeRate($baseCurrency, $targetCurrency, $rateValue, $date);

        $this->repository->save($rate);

        $this->assertDatabaseHas('exchange_rates', [
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => $rateValue,
            'date' => $date->format('Y-m-d 00:00:00'),
        ]);
    }

    public function test_save_updates_existing_rate()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $initialRate = 75.0;
        $updatedRate = 76.0;
        $date = new DateTimeImmutable('2023-10-27');

        // Создаем начальную запись
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => $initialRate,
            'date' => $date->format('Y-m-d 00:00:00'),
        ]);

        $rate = new ExchangeRate($baseCurrency, $targetCurrency, $updatedRate, $date);

        $this->repository->save($rate);

        // Проверяем, что запись обновлена (всё ещё одна)
        $this->assertDatabaseCount('exchange_rates', 1);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => $updatedRate,
            'date' => $date->format('Y-m-d 00:00:00'),
        ]);
    }

    public function test_find_by_date_and_currencies_returns_rate()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $rateValue = 75.5;
        $date = new DateTimeImmutable('2023-10-27');

        // Создаем запись в БД
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => $rateValue,
            'date' => $date->format('Y-m-d 00:00:00'),
        ]);

        $foundRate = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $targetCurrency);

        $this->assertNotNull($foundRate);
        $this->assertInstanceOf(ExchangeRate::class, $foundRate);
        $this->assertEquals($baseCurrency, $foundRate->getBaseCurrencyCode());
        $this->assertEquals($targetCurrency, $foundRate->getTargetCurrencyCode());
        $this->assertEquals($rateValue, $foundRate->getRate());
        $this->assertEquals($date->format('Y-m-d'), $foundRate->getDate()->format('Y-m-d'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $foundRate->getDate());
    }

    public function test_find_by_date_and_currencies_returns_null_if_not_found()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $date = new DateTimeImmutable('2023-10-27');

        // Убеждаемся, что запись отсутствует
        $this->assertDatabaseMissing('exchange_rates', [
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'date' => $date->format('Y-m-d 00:00:00'),
        ]);

        $foundRate = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $targetCurrency);

        $this->assertNull($foundRate);
    }

    public function test_find_historical_rates_returns_array()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $startDate = new DateTimeImmutable('2023-10-25');
        $endDate = new DateTimeImmutable('2023-10-27');

        // Создаем несколько записей
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => 74.0,
            'date' => '2023-10-25 00:00:00',
        ]);
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => 75.0,
            'date' => '2023-10-26 00:00:00',
        ]);
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $baseCurrency,
            'target_currency_code' => $targetCurrency,
            'rate' => 76.0,
            'date' => '2023-10-27 00:00:00',
        ]);

        $rates = $this->repository->findHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

        $this->assertIsArray($rates);
        $this->assertCount(3, $rates);
        $this->assertEquals('2023-10-25', $rates[0]->getDate()->format('Y-m-d'));
        $this->assertEquals('2023-10-26', $rates[1]->getDate()->format('Y-m-d'));
        $this->assertEquals('2023-10-27', $rates[2]->getDate()->format('Y-m-d'));

        foreach ($rates as $rate) {
            $this->assertInstanceOf(ExchangeRate::class, $rate);
            $this->assertInstanceOf(\DateTimeImmutable::class, $rate->getDate());
        }
    }

    public function test_find_historical_rates_returns_empty_array_if_none_found()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $startDate = new DateTimeImmutable('2023-10-25');
        $endDate = new DateTimeImmutable('2023-10-27');

        $rates = $this->repository->findHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

        $this->assertIsArray($rates);
        $this->assertEmpty($rates);
    }
}
