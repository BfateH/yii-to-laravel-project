<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Services\JtiService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class JwtE2eTest extends TestCase
{
    use RefreshDatabase;

    protected $privateKey;
    protected $publicKey;
    protected $jtiServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем кеш перед каждым тестом
        Cache::flush();

        // Генерация тестовых RSA ключей
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 1024,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $this->privateKey = openssl_pkey_new($config);
        $publicKeyDetails = openssl_pkey_get_details($this->privateKey);
        $this->publicKey = $publicKeyDetails['key'];

        // Создаем мок для JtiService и регистрируем его в контейнере
        $this->jtiServiceMock = Mockery::mock(JtiService::class);
        $this->app->instance(JtiService::class, $this->jtiServiceMock);

        // Конфигурация тестового провайдера
        config()->set('sso.providers.test_provider', [
            'jwks_url' => 'https://example.com/jwks',
            'allowed_issuers' => ['https://example.com'],
            'allowed_audiences' => ['test-audience'],
        ]);
    }

    public function test_full_jwt_e2e_flow_with_redirect_to_protected_page()
    {
        // 1. Мокируем внешнюю систему, которая выдает JWT
        Http::fake([
            'https://example.com/jwks' => Http::response([
                'keys' => [
                    [
                        'kid' => 'test-key',
                        'kty' => 'RSA',
                        'alg' => 'RS256',
                        'n' => base64_encode(openssl_pkey_get_details($this->privateKey)['rsa']['n']),
                        'e' => base64_encode(openssl_pkey_get_details($this->privateKey)['rsa']['e']),
                    ]
                ]
            ]),
        ]);

        // Генерируем уникальный JTI
        $jti = uniqid('jti_', true);

        // 2. Генерируем JWT
        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'jti' => $jti,
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        // Настраиваем мок JtiService
        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with($jti, 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with($jti, 'test_provider', Mockery::type('int'));

        // 3. Выполняем POST запрос к /auth/token-login
        $response = $this->post(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        // 4. Проверяем, что запрос выполнен успешно (редирект)
        $response->assertStatus(302);

        // 5. Проверяем, что пользователь создан
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'provider' => 'test_provider',
            'provider_id' => '123',
        ]);

        // 6. Проверяем, что произошел редирект на защищенную страницу
        $response->assertRedirect(route('moonshine.index'));

        // 7. Проверяем, что пользователь аутентифицирован
        $this->assertAuthenticated();

        // 8. Проверяем доступ к защищенной странице
        $protectedPageResponse = $this->get(route('moonshine.index'));
        $protectedPageResponse->assertStatus(200);

        // 9. Проверяем, что сессия установлена
        $this->assertNotNull(session()->getId());
    }

    public function test_jwt_e2e_flow_for_existing_user()
    {
        // Создаем пользователя заранее
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'provider' => 'test_provider',
            'provider_id' => '456',
            'name' => 'Old Name', // Исходное имя
        ]);

        Http::fake([
            'https://example.com/jwks' => Http::response([
                'keys' => [
                    [
                        'kid' => 'test-key',
                        'kty' => 'RSA',
                        'alg' => 'RS256',
                        'n' => base64_encode(openssl_pkey_get_details($this->privateKey)['rsa']['n']),
                        'e' => base64_encode(openssl_pkey_get_details($this->privateKey)['rsa']['e']),
                    ]
                ]
            ]),
        ]);

        // Генерируем уникальный JTI
        $jti = uniqid('jti_', true);

        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '456', // Тот же provider_id, что у существующего пользователя
            'email' => 'existing@example.com',
            'name' => 'Updated Name', // Новое имя
            'jti' => $jti,
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        // Настраиваем мок JtiService
        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with($jti, 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with($jti, 'test_provider', Mockery::type('int'));

        $response = $this->post(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        // Проверяем, что запрос выполнен успешно (редирект)
        $response->assertStatus(302);

        // Проверяем, что пользователь обновил данные
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'name' => 'Updated Name', // Проверяем обновленное имя
        ]);

        // Проверяем редирект и аутентификацию
        $response->assertRedirect(route('moonshine.index'));
        $this->assertAuthenticated();

        // Проверяем, что аутентифицирован правильный пользователь
        $this->assertEquals($user->id, auth()->id());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
