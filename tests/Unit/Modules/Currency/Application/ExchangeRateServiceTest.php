<?php

namespace Tests\Unit\Modules\Currency\Application;

use App\Modules\Currency\Application\ExchangeRateService;
use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Domain\ExchangeRateException;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use App\Modules\Currency\Domain\ExchangeRatesProviderInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ExchangeRateServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    private ExchangeRatesProviderInterface|MockInterface $providerMock;
    private ExchangeRateRepositoryInterface|MockInterface $repositoryMock;
    private LoggerInterface $logger;
    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Устанавливаем конфигурацию перед созданием сервиса
        config(['currency.base_currency' => 'RUB']);

        $this->providerMock = Mockery::mock(ExchangeRatesProviderInterface::class);
        $this->repositoryMock = Mockery::mock(ExchangeRateRepositoryInterface::class);
        $this->logger = new NullLogger();

        $this->service = new ExchangeRateService(
            $this->providerMock,
            $this->repositoryMock,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function dateTimeMatcher(DateTimeImmutable $expectedDate): callable
    {
        return function ($actualDate) use ($expectedDate) {
            if (!$actualDate instanceof \DateTimeInterface) {
                return false;
            }
            return $actualDate->format('Y-m-d') === $expectedDate->format('Y-m-d');
        };
    }

    public function test_convert_same_currency()
    {
        $result = $this->service->convert(100, 'USD', 'USD', new DateTimeImmutable());
        $this->assertEquals(100, $result);
    }

    public function test_convert_base_to_target()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $rate = new ExchangeRate('RUB', 'USD', 75.0, $date);

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'USD'
            )
            ->once()
            ->andReturn($rate);

        $result = $this->service->convert(100, 'RUB', 'USD', $date);
        $this->assertEquals(100 * 75, $result);
    }

    public function test_convert_target_to_base()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $rate = new ExchangeRate('RUB', 'USD', 75.0, $date);

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'USD'
            )
            ->once()
            ->andReturn($rate);

        $result = $this->service->convert(100, 'USD', 'RUB', $date);
        $this->assertEquals(100 / 75, $result);
    }

    public function test_convert_cross_via_base()
    {
        $date = new DateTimeImmutable('2023-10-27');
        $rateRubUsd = new ExchangeRate('RUB', 'USD', 75.0, $date);
        $rateRubEur = new ExchangeRate('RUB', 'EUR', 85.0, $date);

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'USD'
            )
            ->once()
            ->andReturn($rateRubUsd);

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'EUR'
            )
            ->once()
            ->andReturn($rateRubEur);

        $result = $this->service->convert(100, 'USD', 'EUR', $date);
        $this->assertEquals((85 / 75) * 100, $result);
    }

    public function test_convert_throws_exception_if_rate_not_found_base_to_target()
    {
        $date = new DateTimeImmutable('2023-10-27');

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'USD'
            )
            ->once()
            ->andReturn(null);

        $this->expectException(ExchangeRateException::class);
        $this->expectExceptionMessage('Курс RUB -> USD на дату 2023-10-27 не найден.');

        $this->service->convert(100, 'RUB', 'USD', $date);
    }

    public function test_convert_throws_exception_if_rate_not_found_cross()
    {
        $date = new DateTimeImmutable('2023-10-27');

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'USD'
            )
            ->once()
            ->andReturn(new ExchangeRate('RUB', 'USD', 75.0, $date));

        $this->repositoryMock->shouldReceive('findByDateAndCurrencies')
            ->with(
                Mockery::on($this->dateTimeMatcher($date)),
                'RUB',
                'EUR'
            )
            ->once()
            ->andReturn(null);

        $this->expectException(ExchangeRateException::class);
        $this->expectExceptionMessage('Курс для конвертации USD -> EUR через RUB на дату 2023-10-27 не найден.');

        $this->service->convert(100, 'USD', 'EUR', $date);
    }

    public function test_update_rates_success()
    {
        $date = new DateTimeImmutable();
        $rates = [
            new ExchangeRate('RUB', 'USD', 75.0, $date),
            new ExchangeRate('RUB', 'EUR', 85.0, $date),
        ];

        $this->providerMock->allows('getName')->andReturn('test_provider');

        $this->providerMock->shouldReceive('getRates')
            ->with($date)
            ->once()
            ->andReturn($rates);

        $this->repositoryMock->shouldReceive('save')
            ->times(count($rates));

        $this->service->updateRates($date);

        $this->assertTrue(true);
    }

    public function test_update_rates_throws_exception_on_provider_error()
    {
        $date = new DateTimeImmutable();
        $errorMessage = 'API недоступен';

        $this->providerMock->allows('getName')->andReturn('test_provider');

        $this->providerMock->shouldReceive('getRates')
            ->with($date)
            ->once()
            ->andThrow(new \Exception($errorMessage));

        $this->repositoryMock->shouldNotReceive('save');

        $this->expectException(ExchangeRateException::class);
        $this->expectExceptionMessage("Не удалось обновить курсы валют: {$errorMessage}");

        $this->service->updateRates($date);
    }

    public function test_get_historical_rates()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $startDate = new DateTimeImmutable('2023-10-25');
        $endDate = new DateTimeImmutable('2023-10-27');
        $expectedRates = [
            new ExchangeRate('RUB', 'USD', 74.5, $startDate),
            new ExchangeRate('RUB', 'USD', 75.0, $endDate),
        ];

        $this->repositoryMock->shouldReceive('findHistoricalRates')
            ->with($baseCurrency, $targetCurrency, $startDate, $endDate)
            ->once()
            ->andReturn($expectedRates);

        $result = $this->service->getHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

        $this->assertEquals($expectedRates, $result);
    }
}
