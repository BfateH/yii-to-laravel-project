<?php

namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\CalculatorRequest;
use App\Modules\Providers\Shopogolic\DTO\CalculationResultDTO;
use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class CalculatorRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private CalculatorRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new CalculatorRequest($this->clientMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculate_returns_correct_structure()
    {
        $params = [
            'warehouse_id' => 1,
            'weight' => 2.5,
            'country_code' => 'US',
        ];

        $mockApiResponse = [
            'weight' => 2.5,
            'length' => 30.0,
            'width' => 20.0,
            'height' => 10.0,
            'couriers' => [
                [
                    'id' => 101,
                    'name' => 'DHL Express',
                    'warehouse_id' => 1,
                    'calculated_price' => 35.00,
                ],
            ],
        ];

        $this->clientMock
            ->shouldReceive('post')
            ->with('/parcels/rate', $params)
            ->once()
            ->andReturn($mockApiResponse);

        $result = $this->request->calculate($params);

        $this->assertInstanceOf(CalculationResultDTO::class, $result);
        $this->assertEquals(2.5, $result->weight);
        $this->assertEquals(30.0, $result->length);
        $this->assertEquals(20.0, $result->width);
        $this->assertEquals(10.0, $result->height);
        $this->assertIsArray($result->couriers);
        $this->assertCount(1, $result->couriers);
        $this->assertInstanceOf(CourierDTO::class, $result->couriers[0]);
        $this->assertEquals(101, $result->couriers[0]->id);
        $this->assertEquals(35.00, $result->couriers[0]->calculated_price);
    }

    public function test_calculate_throws_exception_on_missing_required_params()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->request->calculate([
            'weight' => 2.5,
            'country_code' => 'US',
        ]);
    }
}
