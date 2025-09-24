<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\ParcelsRequest;
use App\Modules\Providers\Shopogolic\DTO\ParcelDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class ParcelsRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private ParcelsRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new ParcelsRequest($this->clientMock);
    }

    public function test_create_parcel()
    {
        $data = [
            'warehouse_id' => 1,
            'courier_id' => 101,
            'user_id' => 2,
            'address_id' => 3,
            'weight' => 1.5,
            'length' => 20,
            'width' => 15,
            'height' => 10,
            'insurance' => 1
        ];
        $mockResponse = array_merge($data, ['id' => 300, 'status' => 'New', 'status_id' => 50]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('parcels', $data)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->create($data);
        $this->assertInstanceOf(ParcelDTO::class, $result);
        $this->assertEquals(300, $result->id);
    }

    public function test_hold_parcel()
    {
        $mockResponse = ['id' => 300, 'status' => 'Held', 'status_id' => 200, 'warehouse_id' => 1];

        $this->clientMock
            ->shouldReceive('post')
            ->with('parcels/300/hold', ['hold_reason_id' => 300])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->hold(300, 300);
        $this->assertInstanceOf(ParcelDTO::class, $result);
        $this->assertEquals('Held', $result->status);
    }

    public function test_get_by_id_not_found()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('parcels/999')
            ->once()
            ->andThrow(new ShopogolicApiException('Not found', 404));

        $result = $this->request->getById(999);
        $this->assertNull($result);
    }
}
