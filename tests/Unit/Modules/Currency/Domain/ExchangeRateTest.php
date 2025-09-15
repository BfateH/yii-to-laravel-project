<?php

namespace Tests\Unit\Modules\Currency\Domain;

use App\Modules\Currency\Domain\ExchangeRate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExchangeRateTest extends TestCase
{
    public function test_it_can_be_created_and_properties_are_accessible()
    {
        $baseCurrency = 'RUB';
        $targetCurrency = 'USD';
        $rate = 0.013;
        $date = new DateTimeImmutable('2023-10-27');

        $exchangeRate = new ExchangeRate($baseCurrency, $targetCurrency, $rate, $date);

        $this->assertEquals($baseCurrency, $exchangeRate->getBaseCurrencyCode());
        $this->assertEquals($targetCurrency, $exchangeRate->getTargetCurrencyCode());
        $this->assertEquals($rate, $exchangeRate->getRate());
        $this->assertEquals($date, $exchangeRate->getDate());

        $expectedId = 'RUB_USD_2023-10-27';
        $this->assertEquals($expectedId, $exchangeRate->getId());
    }
}
