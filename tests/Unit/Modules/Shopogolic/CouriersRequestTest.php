<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\CouriersRequest;
use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class CouriersRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private CouriersRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new CouriersRequest($this->clientMock);
    }

    public function test_get_by_warehouse_id()
    {
        $warehouseId = 1;
        $mockResponse = [
            'data' => [
                ['id' => 101, 'name' => 'DHL', 'warehouse_id' => 1, 'calculated_price' => 0.0]
            ]
        ];

        $this->clientMock
            ->shouldReceive('get')
            ->with('couriers', ['filter[warehouse_id]' => 1])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->getByWarehouseId($warehouseId);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(CourierDTO::class, $result[0]);
        $this->assertEquals('DHL', $result[0]->name);
    }
}
