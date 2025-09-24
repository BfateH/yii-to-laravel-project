<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\HsCodesRequest;
use App\Modules\Providers\Shopogolic\DTO\HsCodeDTO;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class HsCodesRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private HsCodesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new HsCodesRequest($this->clientMock);
    }

    public function test_get_all_hscodes()
    {
        $mockResponse = [
            'data' => [
                ['id' => 1, 'code' => '1234.56', 'name_ru' => 'Товар', 'name_en' => 'Item']
            ]
        ];

        $this->clientMock
            ->shouldReceive('get')
            ->with('/hscode', ['per-page' => 20, 'page' => 1])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->getAll();
        $this->assertCount(1, $result);
        $this->assertInstanceOf(HsCodeDTO::class, $result[0]);
        $this->assertEquals('1234.56', $result[0]->code);
    }
}
