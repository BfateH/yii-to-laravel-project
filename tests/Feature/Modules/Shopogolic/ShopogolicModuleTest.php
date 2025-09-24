<?php

namespace Tests\Feature\Modules\Shopogolic;

use App\Modules\Providers\ProviderFactory;
use App\Modules\Providers\Shopogolic\DTO\WarehouseDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopogolicModuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array $responses Массив объектов Response
     * @return Client
     */
    protected function createMockedClient(array $responses): Client
    {
        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);

        $guzzleClient = new GuzzleClient([
            'base_uri' => 'https://shopogolic.net/api',
            'handler'  => $handlerStack,
            'timeout'  => 30,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode('test_auth_key:'),
            ],
            'verify' => false,
        ]);

        return new Client($guzzleClient);
    }

    public function test_provider_can_get_warehouses()
    {
        $client = $this->createMockedClient([
            new Response(200, [], json_encode([
                'data' => [
                    ['id' => 17, 'code' => 'US', 'name_en' => 'United States', 'name_ru' => 'США'],
                    ['id' => 45, 'code' => 'CN', 'name_en' => 'China', 'name_ru' => 'Китай'],
                ],
            ])),

            new Response(200, [], json_encode([
                'data' => [
                    [
                        'id' => 1,
                        'country_id' => 17,
                        'currency' => 'USD',
                    ],
                    [
                        'id' => 2,
                        'country_id' => 45,
                        'currency' => 'CNY',
                    ],
                ],
            ])),
        ]);

        $this->app->instance(Client::class, $client);

        $provider = ProviderFactory::make('shopogolic');
        $warehouses = $provider->getWarehouses();

        $this->assertIsArray($warehouses);
        $this->assertCount(2, $warehouses);
        $this->assertInstanceOf(WarehouseDTO::class, $warehouses[0]);
        $this->assertEquals('Warehouse #1 (US)', $warehouses[0]->name);
        $this->assertEquals('US', $warehouses[0]->country_code);
    }

    public function test_provider_can_get_couriers_for_warehouse()
    {
        $client = $this->createMockedClient([
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'id' => 101,
                        'name' => 'DHL Express',
                        'warehouse_id' => 1,
                        'calculated_price' => 25.50,
                    ],
                ],
            ])),
        ]);

        $this->app->instance(Client::class, $client);

        $provider = ProviderFactory::make('shopogolic');
        $couriers = $provider->getCouriers(['warehouse_id' => 1]);

        $this->assertIsArray($couriers);
        $this->assertCount(1, $couriers);
        $this->assertEquals(101, $couriers[0]->id);
        $this->assertEquals('DHL Express', $couriers[0]->name);
    }

    public function test_provider_can_calculate_shipping()
    {
        $client = $this->createMockedClient([
            new Response(200, [], json_encode([
                'weight' => 2.5,
                'length' => 30,
                'width' => 20,
                'height' => 10,
                'couriers' => [
                    [
                        'id' => 101,
                        'name' => 'DHL Express',
                        'warehouse_id' => 1,
                        'calculated_price' => 35.00,
                    ],
                ],
            ])),
        ]);

        $this->app->instance(Client::class, $client);

        $provider = ProviderFactory::make('shopogolic');
        $params = [
            'warehouse_id' => 1,
            'weight' => 2.5,
            'country_code' => 'US',
        ];
        $results = $provider->calculateShipping($params);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals(2.5, $results[0]->weight);
        $this->assertCount(1, $results[0]->couriers);
        $this->assertEquals(35.00, $results[0]->couriers[0]->calculated_price);
    }
}
