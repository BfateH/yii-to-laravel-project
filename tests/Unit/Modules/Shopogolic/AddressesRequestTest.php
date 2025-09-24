<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\AddressesRequest;
use App\Modules\Providers\Shopogolic\DTO\AddressDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class AddressesRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private AddressesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new AddressesRequest($this->clientMock);
    }

    public function test_create_address()
    {
        $addressData = ['country_code' => 'RU', 'zipcode' => '123456', 'phone' => '+79991234567'];
        $mockResponse = array_merge($addressData, ['id' => 100]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('addresses', $addressData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->create($addressData);
        $this->assertInstanceOf(AddressDTO::class, $result);
        $this->assertEquals(100, $result->id);
    }

    public function test_update_address()
    {
        $addressId = 100;
        $updateData = ['city' => 'Moscow'];
        $mockResponse = ['id' => 101, 'city' => 'Moscow', 'country_code' => 'RU', 'zipcode' => '', 'phone' => ''];

        $this->clientMock
            ->shouldReceive('put')
            ->with("addresses/{$addressId}", $updateData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->update($addressId, $updateData);
        $this->assertInstanceOf(AddressDTO::class, $result);
        $this->assertEquals(101, $result->id);
    }

    public function test_get_all_with_filters_and_expand()
    {
        $filters = ['user_id' => 123];
        $expand = ['user', 'country'];
        $mockResponse = [
            'data' => [
                ['id' => 1, 'user_id' => 123, 'country_code' => 'RU', 'zipcode' => '', 'phone' => '']
            ]
        ];

        $this->clientMock
            ->shouldReceive('get')
            ->with('addresses', ['user_id' => 123, 'expand' => 'user,country'])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->getAll($filters, $expand);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(AddressDTO::class, $result[0]);
    }

    public function test_get_by_id_not_found_returns_null()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('addresses/999', [])
            ->once()
            ->andThrow(new ShopogolicApiException('Not found', 404));

        $result = $this->request->getById(999);
        $this->assertNull($result);
    }

    public function test_delete_address_success()
    {
        $this->clientMock
            ->shouldReceive('delete')
            ->with('addresses/100')
            ->once()
            ->andReturn([]);

        $result = $this->request->delete(100);
        $this->assertTrue($result);
    }
}
