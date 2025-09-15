<?php

namespace Tests\Unit\Modules\Currency\Infrastructure\Decorators;

use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use App\Modules\Currency\Infrastructure\Decorators\ExchangeRateCacheDecorator;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class ExchangeRateCacheDecoratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ExchangeRateRepositoryInterface|MockInterface $repositoryMock;
    private ExchangeRateCacheDecorator $decorator;
    private int $ttlMinutes = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->repositoryMock = Mockery::mock(ExchangeRateRepositoryInterface::class);
        $this->decorator = new ExchangeRateCacheDecorator($this->repositoryMock, $this->ttlMinutes);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_find_by_date_and_currencies_returns_from_cache_if_available()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $expectedRate = new ExchangeRate($baseCurrency, $targetCurrency, 75.5, $date);

        $cacheKey = "exchange_rate_{$baseCurrency}_{$targetCurrency}_" . $date->format('Y-m-d');
        Cache::put($cacheKey, $expectedRate, $this->ttlMinutes * 60);

        $this->repositoryMock->shouldNotReceive('findByDateAndCurrencies');

        $result = $this->decorator->findByDateAndCurrencies($date, $baseCurrency, $targetCurrency);

        $this->assertEquals($expectedRate, $result);
    }

    public function test_find_by_date_and_currencies_calls_repository_and_caches_result()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $expectedRate = new ExchangeRate($baseCurrency, $targetCurrency, 75.5, $date);
        $cacheKey = "exchange_rate_{$baseCurrency}_{$targetCurrency}_" . $date->format('Y-m-d');

        $this->assertFalse(Cache::has($cacheKey));

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with($date, $baseCurrency, $targetCurrency)
            ->once()
            ->andReturn($expectedRate);

        $result = $this->decorator->findByDateAndCurrencies($date, $baseCurrency, $targetCurrency);

        $this->assertEquals($expectedRate, $result);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($expectedRate, Cache::get($cacheKey));
    }

    public function test_find_by_date_and_currencies_does_not_cache_null_result()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $baseCurrency = 'RUB';
        $targetCurrency = 'XYZ';
        $cacheKey = "exchange_rate_{$baseCurrency}_{$targetCurrency}_" . $date->format('Y-m-d');

        $this->assertFalse(Cache::has($cacheKey));

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with($date, $baseCurrency, $targetCurrency)
            ->once()
            ->andReturn(null);

        $result = $this->decorator->findByDateAndCurrencies($date, $baseCurrency, $targetCurrency);

        $this->assertNull($result);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_save_invalidates_cache_for_single_rate()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $rate = new ExchangeRate($baseCurrency, $targetCurrency, 75.5, $date);
        $cacheKey = "exchange_rate_{$baseCurrency}_{$targetCurrency}_" . $date->format('Y-m-d');

        Cache::put($cacheKey, 'old_value', $this->ttlMinutes * 60);
        $this->assertTrue(Cache::has($cacheKey));

        $this->repositoryMock->shouldReceive('save')
            ->with($rate)
            ->once();

        $this->decorator->save($rate);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_find_historical_rates_returns_from_cache_if_available()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $startDate = new DateTimeImmutable('2023-10-25');
        $endDate = new DateTimeImmutable('2023-10-27');
        $expectedRates = [
            new ExchangeRate($baseCurrency, $targetCurrency, 74.0, $startDate),
            new ExchangeRate($baseCurrency, $targetCurrency, 75.0, $endDate),
        ];

        $cacheKey = "historical_rates_{$baseCurrency}_{$targetCurrency}_" .
            $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');

        Cache::put($cacheKey, $expectedRates, $this->ttlMinutes * 60);

        $this->repositoryMock->shouldNotReceive('findHistoricalRates');

        $result = $this->decorator->findHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

        $this->assertEquals($expectedRates, $result);
    }

    public function test_find_historical_rates_calls_repository_and_caches_result()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $startDate = new DateTimeImmutable('2023-10-25');
        $endDate = new DateTimeImmutable('2023-10-27');
        $expectedRates = [
            new ExchangeRate($baseCurrency, $targetCurrency, 74.0, $startDate),
            new ExchangeRate($baseCurrency, $targetCurrency, 75.0, $endDate),
        ];

        $cacheKey = "historical_rates_{$baseCurrency}_{$targetCurrency}_" .
            $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');

        $this->assertFalse(Cache::has($cacheKey));

        $this->repositoryMock->shouldReceive('findHistoricalRates')
            ->with($baseCurrency, $targetCurrency, $startDate, $endDate)
            ->once()
            ->andReturn($expectedRates);

        $result = $this->decorator->findHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

        $this->assertEquals($expectedRates, $result);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($expectedRates, Cache::get($cacheKey));
    }

    public function test_find_historical_rates_returns_empty_array_if_repository_returns_empty()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'XYZ';
        $startDate = new DateTimeImmutable('2023-10-25');
        $endDate = new DateTimeImmutable('2023-10-27');
        $expectedRates = [];

        $cacheKey = "historical_rates_{$baseCurrency}_{$targetCurrency}_" .
            $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');

        $this->assertFalse(Cache::has($cacheKey));

        $this->repositoryMock->shouldReceive('findHistoricalRates')
            ->with($baseCurrency, $targetCurrency, $startDate, $endDate)
            ->once()
            ->andReturn($expectedRates);

        $result = $this->decorator->findHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

        $this->assertEquals($expectedRates, $result);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($expectedRates, Cache::get($cacheKey));
    }
}
