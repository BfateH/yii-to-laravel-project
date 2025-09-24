<?php

namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\DTO\AddressDTO;
use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use App\Modules\Providers\Shopogolic\DTO\CountryDTO;
use App\Modules\Providers\Shopogolic\DTO\CityDTO;
use App\Modules\Providers\Shopogolic\DTO\HsCodeDTO;
use App\Modules\Providers\Shopogolic\DTO\OrderDTO;
use App\Modules\Providers\Shopogolic\DTO\ParcelDTO;
use App\Modules\Providers\Shopogolic\DTO\RegionDTO;
use App\Modules\Providers\Shopogolic\DTO\UserDTO;
use App\Modules\Providers\Shopogolic\DTO\WarehouseDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use App\Modules\Providers\ShopogolicProvider;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class ShopogolicProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private ShopogolicProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = Mockery::mock(Client::class);
        $this->provider = new ShopogolicProvider($this->clientMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_warehouses_delegates_to_warehouses_request()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('countries', [
                'per-page' => 20,
                'page' => 1,
            ])
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 17, 'code' => 'US', 'name_en' => 'United States', 'name_ru' => 'США'],
                ],
            ]);

        $this->clientMock
            ->shouldReceive('get')
            ->with('warehouses', [])
            ->once()
            ->andReturn([
                'data' => [
                    [
                        'id' => 1,
                        'country_id' => 17,
                        'currency' => 'USD',
                    ],
                ],
            ]);

        $result = $this->provider->getWarehouses();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(WarehouseDTO::class, $result[0]);
        $this->assertEquals(1, $result[0]->id);
        $this->assertEquals('Warehouse #1 (US)', $result[0]->name);
        $this->assertEquals('US', $result[0]->country_code);
        $this->assertEquals('USD', $result[0]->currency);
    }

    public function test_get_couriers_returns_empty_if_no_warehouse_id()
    {
        $result = $this->provider->getCouriers([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_couriers_calls_correct_endpoint_with_warehouse_id()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('couriers', ['filter[warehouse_id]' => 5])
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 101, 'name' => 'DHL', 'warehouse_id' => 5, 'calculated_price' => 0.0],
                ],
            ]);

        $result = $this->provider->getCouriers(['warehouse_id' => 5]);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CourierDTO::class, $result[0]);
        $this->assertEquals(101, $result[0]->id);
        $this->assertEquals('DHL', $result[0]->name);
    }

    public function test_get_orders_calls_correct_endpoint_with_filters()
    {
        $filters = ['warehouse_id' => 1, 'user_id' => 2];
        $this->clientMock
            ->shouldReceive('get')
            ->with('orders', [
                'filter[warehouse_id]' => 1,
                'filter[user_id]' => 2,
                'page' => 1,
                'per-page' => 20
            ])
            ->once()
            ->andReturn([
                'data' => [
                    [
                        'id' => 100,
                        'warehouse_id' => 1,
                        'user_id' => 2,
                        'status_id' => 100,
                        'status' => 'Draft',
                    ],
                ],
            ]);

        $result = $this->provider->getOrders($filters);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(OrderDTO::class, $result[0]);
        $this->assertEquals(100, $result[0]->id);
    }

    public function test_create_order_calls_post_with_data()
    {
        $orderData = ['warehouse_id' => 1, 'user_id' => 2, 'track' => 'ABC123'];
        $mockResponse = array_merge($orderData, [
            'id' => 200,
            'status_id' => 100,
            'status' => 'Draft',
        ]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('orders', $orderData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->createOrder($orderData);

        $this->assertInstanceOf(OrderDTO::class, $result);
        $this->assertEquals(200, $result->id);
    }

    public function test_pay_order_calls_correct_endpoint()
    {
        $mockResponse = [
            'id' => 200,
            'status' => 'Paid',
            'status_id' => 400,
            'warehouse_id' => 1,
            'user_id' => 2,
        ];

        $this->clientMock
            ->shouldReceive('post')
            ->with('orders/200/paid')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->payOrder(200);

        $this->assertInstanceOf(OrderDTO::class, $result);
        $this->assertEquals('Paid', $result->status);
    }

    public function test_get_order_by_id_not_found_returns_null()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('orders/999')
            ->once()
            ->andThrow(new \App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException('Not found', 404));

        $result = $this->provider->getOrderById(999);
        $this->assertNull($result);
    }

    public function test_get_parcels_calls_correct_endpoint()
    {
        $filters = ['user_id' => 3];
        $this->clientMock
            ->shouldReceive('get')
            ->with('parcels', ['filter[user_id]' => 3])
            ->once()
            ->andReturn([
                'data' => [
                    [
                        'id' => 300,
                        'user_id' => 3,
                        'status' => 'New',
                        'status_id' => 50,
                        'warehouse_id' => 1,
                        'courier_id' => 101,
                        'address_id' => 4,
                        'weight' => 1.0,
                        'length' => 10.0,
                        'width' => 10.0,
                        'height' => 10.0,
                        'insurance' => 0,
                    ],
                ],
            ]);

        $result = $this->provider->getParcels($filters);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ParcelDTO::class, $result[0]);
        $this->assertEquals(300, $result[0]->id);
    }

    public function test_pay_parcel_calls_correct_endpoint()
    {
        $mockResponse = [
            'id' => 300,
            'status' => 'Paid',
            'status_id' => 250,
            'warehouse_id' => 1,
            'courier_id' => 101,
            'user_id' => 3,
            'address_id' => 4,
            'weight' => 1.0,
            'length' => 10.0,
            'width' => 10.0,
            'height' => 10.0,
            'insurance' => 0,
        ];

        $this->clientMock
            ->shouldReceive('post')
            ->with('parcels/300/paid')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->payParcel(300);
        $this->assertInstanceOf(ParcelDTO::class, $result);
        $this->assertEquals('Paid', $result->status);
    }

    public function test_hold_parcel_calls_correct_endpoint()
    {
        $mockResponse = [
            'id' => 300,
            'status' => 'Held',
            'status_id' => 200,
            'warehouse_id' => 1,
            'courier_id' => 101,
            'user_id' => 3,
            'address_id' => 4,
            'weight' => 1.0,
            'length' => 10.0,
            'width' => 10.0,
            'height' => 10.0,
            'insurance' => 0,
        ];

        $this->clientMock
            ->shouldReceive('post')
            ->with('parcels/300/hold', ['hold_reason_id' => 300])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->holdParcel(300, 300);
        $this->assertInstanceOf(ParcelDTO::class, $result);
        $this->assertEquals('Held', $result->status);
    }

    public function test_calculate_shipping_returns_calculation_result_in_array()
    {
        $params = [
            'warehouse_id' => 1,
            'weight' => 2.5,
            'length' => 30.0,
            'width' => 20.0,
            'height' => 10.0,
            'address' => [
                'country_code' => 'US',
                'zipcode' => '12345',
            ]
        ];

        $mockResponse = [
            'weight' => 2.5,
            'length' => 30.0,
            'width' => 20.0,
            'height' => 10.0,
            'couriers' => [
                ['id' => 101, 'name' => 'DHL', 'warehouse_id' => 1, 'calculated_price' => 35.0],
            ],
        ];

        $this->clientMock
            ->shouldReceive('post')
            ->with('parcels/rate', $params)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->calculateShipping($params);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(\App\Modules\Providers\Shopogolic\DTO\CalculationResultDTO::class, $result[0]);
    }

    public function test_create_user_calls_correct_endpoint()
    {
        $userData = ['email' => 'test@example.com', 'name' => 'Test User'];
        $mockResponse = array_merge($userData, ['id' => 50]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('users', $userData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->createUser($userData);
        $this->assertInstanceOf(UserDTO::class, $result);
        $this->assertEquals(50, $result->id);
    }

    public function test_get_user_by_id_not_found_returns_null()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('users/999')
            ->once()
            ->andThrow(new \App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException('Not found', 404));

        $result = $this->provider->getUserById(999);
        $this->assertNull($result);
    }

    public function test_create_address_calls_correct_endpoint()
    {
        $addressData = ['country_code' => 'RU', 'zipcode' => '123456', 'phone' => '+79991234567'];
        $mockResponse = array_merge($addressData, ['id' => 100]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('addresses', $addressData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->provider->createAddress($addressData);
        $this->assertInstanceOf(AddressDTO::class, $result);
        $this->assertEquals(100, $result->id);
    }

    public function test_delete_address_calls_delete_endpoint()
    {
        $this->clientMock
            ->shouldReceive('delete')
            ->with('addresses/100')
            ->once()
            ->andReturn([]);

        $result = $this->provider->deleteAddress(100);
        $this->assertTrue($result);
    }

    public function test_get_countries_calls_correct_endpoint()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('countries', ['per-page' => 20, 'page' => 1])
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 17, 'code' => 'US', 'name_en' => 'United States', 'name_ru' => 'США'],
                ],
            ]);

        $result = $this->provider->getCountries();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CountryDTO::class, $result[0]);
        $this->assertEquals('US', $result[0]->code);
    }

    public function test_get_regions_calls_correct_endpoint()
    {
        $filters = ['country_code' => 'RU'];
        $expand = ['country'];
        $this->clientMock
            ->shouldReceive('get')
            ->with('regions', [
                'page' => 1,
                'filter[country_code]' => 'RU',
                'expand' => 'country',
            ])
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 1, 'name' => 'Moscow Oblast', 'country_id' => 17],
                ],
            ]);

        $result = $this->provider->getRegions($filters, $expand);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(RegionDTO::class, $result[0]);
        $this->assertEquals('Moscow Oblast', $result[0]->name);
    }

    public function test_get_cities_calls_correct_endpoint()
    {
        $filters = ['name' => 'Moscow'];
        $expand = ['country'];
        $this->clientMock
            ->shouldReceive('get')
            ->with('cities', [
                'page' => 1,
                'filter[name][like]' => 'Moscow',
                'expand' => 'country',
            ])
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 1, 'name' => 'Moscow', 'country_id' => 17],
                ],
            ]);

        $result = $this->provider->getCities($filters, $expand);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CityDTO::class, $result[0]);
        $this->assertEquals('Moscow', $result[0]->name);
    }

    public function test_get_hs_codes_calls_correct_endpoint()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('hscode', ['per-page' => 20, 'page' => 1])
            ->once()
            ->andReturn([
                'data' => [
                    ['id' => 1, 'code' => '1234.56', 'name_ru' => 'Товар', 'name_en' => 'Item'],
                ],
            ]);

        $result = $this->provider->getHsCodes();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(HsCodeDTO::class, $result[0]);
        $this->assertEquals('1234.56', $result[0]->code);
    }
}
