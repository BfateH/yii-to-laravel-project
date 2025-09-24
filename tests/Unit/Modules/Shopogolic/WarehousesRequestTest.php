<?php

namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\WarehousesRequest;
use App\Modules\Providers\Shopogolic\DTO\WarehouseDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class WarehousesRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private WarehousesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new WarehousesRequest($this->clientMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_all_calls_correct_endpoint()
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

        $result = $this->request->getAll();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(WarehouseDTO::class, $result[0]);
        $this->assertEquals(1, $result[0]->id);
        $this->assertEquals('Warehouse #1 (US)', $result[0]->name);
        $this->assertEquals('US', $result[0]->country_code);
        $this->assertEquals('USD', $result[0]->currency);
    }

    public function test_map_to_dto_handles_missing_country_gracefully()
    {
        $rawData = [
            'id' => 2,
            'country_id' => 999,
            'currency' => 'EUR',
        ];

        $reflection = new \ReflectionClass($this->request);
        $property = $reflection->getProperty('countryCache');
        $property->setAccessible(true);
        $property->setValue($this->request, []);

        $dto = $this->invokeMethod($this->request, 'mapToDTO', [$rawData]);

        $this->assertInstanceOf(WarehouseDTO::class, $dto);
        $this->assertEquals(2, $dto->id);
        $this->assertEquals('Warehouse #2 (XX)', $dto->name);
        $this->assertEquals('XX', $dto->country_code);
        $this->assertEquals('EUR', $dto->currency);
    }

    /**
     * @param object $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     */
    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
