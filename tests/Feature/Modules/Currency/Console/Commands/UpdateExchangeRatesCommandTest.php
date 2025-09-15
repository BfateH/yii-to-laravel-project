<?php

namespace Tests\Feature\Modules\Currency\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateExchangeRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_rates_successfully()
    {
        // Подготавливаем XML
        $xmlResponse = <<<XML
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs Date="08.09.2025" name="Foreign Currency Market">
    <Valute ID="R01235">
        <NumCode>840</NumCode>
        <CharCode>USD</CharCode>
        <Nominal>1</Nominal>
        <Name>Доллар США</Name>
        <Value>75,0000</Value>
    </Valute>
</ValCurs>
XML;

        // Мокаем HTTP-запрос
        Http::fake([
            'https://www.cbr.ru/scripts/XML_daily.asp?date_req=08/09/2025' => Http::response($xmlResponse, 200),
        ]);

        // Убеждаемся, что таблица пуста
        $this->assertDatabaseCount('exchange_rates', 0);

        $this->artisan('currency:update-rates 2025-09-08')
            ->assertExitCode(0);

        // Проверяем, что данные сохранились
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency_code' => 'RUB',
            'target_currency_code' => 'USD',
            'date' => '2025-09-08 00:00:00',
        ]);

        // Проверяем значение rate отдельно
        $rate = DB::table('exchange_rates')
            ->where('base_currency_code', 'RUB')
            ->where('target_currency_code', 'USD')
            ->where('date', '2025-09-08 00:00:00')
            ->value('rate');

        $expectedRate = 1 / 75.0;
        $this->assertEqualsWithDelta($expectedRate, $rate, 0.000000000001);

        $this->assertDatabaseCount('exchange_rates', 1);
    }

    public function test_it_handles_api_error_gracefully()
    {
        Http::fake([
            'https://www.cbr.ru/scripts/XML_daily.asp*' => Http::response('Not Found', 404),
        ]);

        $this->assertDatabaseCount('exchange_rates', 0);

        $this->artisan('currency:update-rates 2025-09-08')
            ->assertExitCode(1);

        // Проверяем, что данные НЕ сохранились
        $this->assertDatabaseCount('exchange_rates', 0);
    }
}
