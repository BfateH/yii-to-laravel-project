<?php
namespace Tests\Unit\Modules\Shopogolic;

use App\Modules\Providers\Shopogolic\Actions\UsersRequest;
use App\Modules\Providers\Shopogolic\DTO\UserDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;
use App\Modules\Providers\Shopogolic\Http\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class UsersRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Client|MockInterface $clientMock;
    private UsersRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientMock = Mockery::mock(Client::class);
        $this->request = new UsersRequest($this->clientMock);
    }

    public function test_create_user()
    {
        $userData = ['email' => 'test@example.com', 'name' => 'Test User'];
        $mockResponse = array_merge($userData, ['id' => 50]);

        $this->clientMock
            ->shouldReceive('post')
            ->with('users', $userData)
            ->once()
            ->andReturn($mockResponse);

        $result = $this->request->create($userData);
        $this->assertInstanceOf(UserDTO::class, $result);
        $this->assertEquals(50, $result->id);
    }

    public function test_get_by_id_not_found()
    {
        $this->clientMock
            ->shouldReceive('get')
            ->with('users/999')
            ->once()
            ->andThrow(new ShopogolicApiException('Not found', 404));

        $result = $this->request->getById(999);
        $this->assertNull($result);
    }
}
