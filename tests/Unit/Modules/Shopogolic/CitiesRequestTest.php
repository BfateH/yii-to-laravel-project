<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\CitiesRequest;
use App\Modules\Providers\Shopogolic\DTO\CityDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class CitiesRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private CitiesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new CitiesRequest($this->clientMock);
    }

    public function test_get_all_with_filters()
    {
        $filters = ['name' => 'Moscow', 'region_id' => 5];
        $expand = ['country'];
        $mockResponse = [
            'data' => [
                ['id' => 1, 'name' => 'Moscow', 'country_id' => 17, 'region_id' => 5]
            ]
        ];

        $this->clientMock
            ->shouldReceive('get')
            ->with('/cities', [
                'page' => 1,
                'filter[name][like]' => 'Moscow',
                'filter[region_id]' => 5,
                'expand' => 'country'
            ])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->getAll($filters, $expand);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(CityDTO::class, $result[0]);
        $this->assertEquals('Moscow', $result[0]->name);
    }
}
