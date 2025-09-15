<?php

namespace Tests\Unit\Modules\Currency\Infrastructure\Providers;

use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Infrastructure\Providers\CBRProvider;
use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CBRProviderTest extends TestCase
{
    public function test_get_rates_success()
    {
        $provider = new CBRProvider();
        $date = new DateTimeImmutable('2023-10-27');
        $formattedDate = $date->format('d/m/Y');

        // Подготавливаем XML
        $xmlResponse = <<<XML
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs Date="27.10.2023" name="Foreign Currency Market">
    <Valute ID="R01235">
        <NumCode>840</NumCode>
        <CharCode>USD</CharCode>
        <Nominal>1</Nominal>
        <Name>Доллар США</Name>
        <Value>75,5000</Value>
    </Valute>
    <Valute ID="R01239">
        <NumCode>978</NumCode>
        <CharCode>EUR</CharCode>
        <Nominal>1</Nominal>
        <Name>Евро</Name>
        <Value>82,0000</Value>
    </Valute>
</ValCurs>
XML;

        Http::fake([
            "https://www.cbr.ru/scripts/XML_daily.asp?date_req={$formattedDate}" => Http::response($xmlResponse, 200),
        ]);

        $rates = $provider->getRates($date);

        $this->assertCount(2, $rates);
        $this->assertInstanceOf(ExchangeRate::class, $rates[0]);
        $this->assertEquals('RUB', $rates[0]->getBaseCurrencyCode());
        $this->assertEquals('USD', $rates[0]->getTargetCurrencyCode());

        $this->assertEqualsWithDelta(1 / 75.5, $rates[0]->getRate(), 0.0001);
        $this->assertEquals($date->format('Y-m-d'), $rates[0]->getDate()->format('Y-m-d'));

        $this->assertInstanceOf(ExchangeRate::class, $rates[1]);
        $this->assertEquals('RUB', $rates[1]->getBaseCurrencyCode());
        $this->assertEquals('EUR', $rates[1]->getTargetCurrencyCode());
        $this->assertEqualsWithDelta(1 / 82.0, $rates[1]->getRate(), 0.0001);
        $this->assertEquals($date->format('Y-m-d'), $rates[1]->getDate()->format('Y-m-d'));

        // Проверяем, что был сделан правильный запрос
        Http::assertSent(function (Request $request) use ($formattedDate) {
            return $request->url() === "https://www.cbr.ru/scripts/XML_daily.asp?date_req={$formattedDate}";
        });
    }

    public function test_get_rates_throws_exception_on_http_error()
    {
        $provider = new CBRProvider();
        $date = new DateTimeImmutable('2023-10-27');

        Http::fake([
            'https://www.cbr.ru/scripts/XML_daily.asp*' => Http::response('Not Found', 404),
        ]);

        $this->expectException(\Exception::class);

        $provider->getRates($date);
    }

    public function test_get_rates_throws_exception_on_xml_parse_error()
    {
        $provider = new CBRProvider();
        $date = new DateTimeImmutable('2023-10-27');

        Http::fake([
            'https://www.cbr.ru/scripts/XML_daily.asp*' => Http::response('Invalid XML', 200), // Валидный HTTP, но невалидный XML
        ]);

        $this->expectException(\Exception::class);

        $provider->getRates($date);
    }

    public function test_get_name()
    {
        $provider = new CBRProvider();
        $this->assertEquals('cbr', $provider->getName());
    }
}
