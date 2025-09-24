<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\OrdersRequest;
use App\Modules\Providers\Shopogolic\DTO\OrderDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class OrdersRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private OrdersRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new OrdersRequest($this->clientMock);
    }

    public function test_get_filtered_orders()
    {
        $filters = ['warehouse_id' => 1, 'user_id' => 2];
        $mockResponse = [
            'data' => [
                ['id' => 100, 'warehouse_id' => 1, 'user_id' => 2, 'status' => 'Draft', 'status_id' => 100]
            ]
        ];

        $this->clientMock
            ->shouldReceive('get')
            ->with('orders', [
                'filter[warehouse_id]' => 1,
                'filter[user_id]' => 2,
                'page' => 1,
                'per-page' => 20
            ])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->getFiltered($filters);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(OrderDTO::class, $result[0]);
    }

    public function test_get_by_id_not_found()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('orders/999')
            ->once()
            ->andThrow(new ShopogolicApiException('Not found', 404));

        $result = $this->request->getById(999);
        $this->assertNull($result);
    }

    public function test_create_order()
    {
        $orderData = ['warehouse_id' => 1, 'user_id' => 2, 'track' => 'ABC123'];
        $mockResponse = array_merge($orderData, ['id' => 200, 'status' => 'Draft', 'status_id' => 100]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('orders', $orderData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->create($orderData);
        $this->assertInstanceOf(OrderDTO::class, $result);
        $this->assertEquals(200, $result->id);
    }

    public function test_pay_order()
    {
        $mockResponse = ['id' => 200, 'status' => 'Paid', 'status_id' => 400, 'warehouse_id' => 1, 'user_id' => 2];

        $this->clientMock
            ->shouldReceive('post')
            ->with('orders/200/paid')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->pay(200);
        $this->assertInstanceOf(OrderDTO::class, $result);
        $this->assertEquals('Paid', $result->status);
    }
}
