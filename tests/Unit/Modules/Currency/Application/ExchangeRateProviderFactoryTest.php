<?php

namespace Tests\Unit\Modules\Currency\Application;

use App\Modules\Currency\Infrastructure\Factories\ExchangeRateProviderFactory;
use App\Modules\Currency\Infrastructure\Providers\CBRProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExchangeRateProviderFactoryTest extends TestCase
{
    public function test_it_creates_cbr_provider()
    {
        $factory = new ExchangeRateProviderFactory();

        $provider = $factory->create('cbr');

        $this->assertInstanceOf(CBRProvider::class, $provider);
        $this->assertEquals('cbr', $provider->getName());
    }

    public function test_it_throws_exception_for_unknown_provider()
    {
        $factory = new ExchangeRateProviderFactory();
        $unknownProviderName = 'unknown_provider';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Неизвестный провайдер курсов валют: {$unknownProviderName}");

        $factory->create($unknownProviderName);
    }
}
