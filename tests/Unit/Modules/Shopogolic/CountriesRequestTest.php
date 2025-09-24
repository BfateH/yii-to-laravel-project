<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\CountriesRequest;
use App\Modules\Providers\Shopogolic\DTO\CountryDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class CountriesRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private CountriesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new CountriesRequest($this->clientMock);
    }

    public function test_get_all_countries()
    {
        $mockResponse = [
            'data' => [
                ['id' => 17, 'code' => 'US', 'name_en' => 'United States', 'name_ru' => 'США']
            ]
        ];

        $this->clientMock
            ->shouldReceive('get')
            ->with('/countries', ['per-page' => 20, 'page' => 1])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->getAll();
        $this->assertCount(1, $result);
        $this->assertInstanceOf(CountryDTO::class, $result[0]);
        $this->assertEquals('US', $result[0]->code);
    }
}
