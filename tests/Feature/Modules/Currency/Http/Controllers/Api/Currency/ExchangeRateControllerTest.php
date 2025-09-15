<?php

namespace Tests\Feature\Modules\Currency\Http\Controllers\Api\Currency;

use App\Models\ExchangeRateModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_endpoint_returns_converted_amount()
    {
        $date = '2023-10-27';
        // Заполняем БД необходимыми курсами
        ExchangeRateModel::factory()->create([
            'base_currency_code' => 'RUB',
            'target_currency_code' => 'USD',
            'rate' => 75.0, // RUB/USD
            'date' => $date,
        ]);
        ExchangeRateModel::factory()->create([
            'base_currency_code' => 'RUB',
            'target_currency_code' => 'EUR',
            'rate' => 85.0, // RUB/EUR
            'date' => $date,
        ]);

        $response = $this->getJson("/api/exchange-rates/convert?amount=100&from=USD&to=EUR&date={$date}");

        $response->assertStatus(200);

        $expectedRateUsed = 85.0 / 75.0;
        $expectedConvertedAmount = 100 * $expectedRateUsed;

        $response->assertJson([
            'converted_amount' => $expectedConvertedAmount,
            'rate_used' => $expectedRateUsed,
            'date' => $date,
        ]);
    }

    public function test_convert_endpoint_returns_error_if_rate_not_found()
    {
        // Не заполняем БД курсами
        $response = $this->getJson('/api/exchange-rates/convert?amount=100&from=USD&to=EUR&date=2023-10-27');

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);

        // Проверяем содержимое ответа
        $responseData = $response->json();
        $this->assertStringContainsString('Курс', $responseData['error']);
    }

    public function test_history_endpoint_returns_rates()
    {
        $base = 'RUB';
        $target = 'USD';
        $startDate = '2023-10-25';
        $endDate = '2023-10-27';

        // Заполняем БД историческими курсами
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $base,
            'target_currency_code' => $target,
            'rate' => 74.0,
            'date' => '2023-10-25',
        ]);
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $base,
            'target_currency_code' => $target,
            'rate' => 75.0,
            'date' => '2023-10-26',
        ]);
        ExchangeRateModel::factory()->create([
            'base_currency_code' => $base,
            'target_currency_code' => $target,
            'rate' => 76.0,
            'date' => '2023-10-27',
        ]);

        $response = $this->getJson("/api/exchange-rates/history?base={$base}&target={$target}&start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJson([
            ['date' => '2023-10-25', 'rate' => 74.0],
            ['date' => '2023-10-26', 'rate' => 75.0],
            ['date' => '2023-10-27', 'rate' => 76.0],
        ]);
    }

    public function test_convert_endpoint_validates_input()
    {
        $response = $this->getJson('/api/exchange-rates/convert?amount=abc&from=USD&to=EUR'); // amount не число

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Ошибка валидации']);
    }

    public function test_history_endpoint_validates_input()
    {
        $response = $this->getJson('/api/exchange-rates/history?base=RUB&target=USD&start_date=invalid-date&end_date=2023-10-27');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Ошибка валидации']);
    }
}
