<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\JtiService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class JwtAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $jtiServiceMock;
    protected $privateKey;
    protected $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Генерация тестовых RSA ключей
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 1024,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $this->privateKey = openssl_pkey_new($config);
        $publicKeyDetails = openssl_pkey_get_details($this->privateKey);
        $this->publicKey = $publicKeyDetails['key'];

        // Мокируем JtiService
        $this->jtiServiceMock = $this->mock(JtiService::class);

        // Конфигурация тестового провайдера
        config()->set('sso.providers.test_provider', [
            'jwks_url' => 'https://example.com/jwks',
            'allowed_issuers' => ['https://example.com'],
            'allowed_audiences' => ['test-audience'],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_valid_token_authenticates_user()
    {
        // Мокируем JWKS endpoint
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

        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'jti' => 'unique-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        // Мокируем проверку JTI
        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with('unique-jti', 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with('unique-jti', 'test_provider', \Mockery::type('int'));

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'email', 'name'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'provider' => 'test_provider',
        ]);
    }

    public function test_expired_token_returns_401()
    {
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

        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->subHour()->timestamp, // Просрочен
            'iat' => now()->subDay()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_invalid_signature_returns_401()
    {
        // Генерируем другой ключ для создания невалидной подписи
        $differentPrivateKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 1024,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
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

        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
        ];

        // Подписываем другим ключом
        $token = JWT::encode($payload, $differentPrivateKey, 'RS256', 'test-key');

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);

    }

    public function test_invalid_issuer_returns_401()
    {
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

        $payload = [
            'iss' => 'https://invalid-issuer.com', // Неверный issuer
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_invalid_audience_returns_401()
    {
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

        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'invalid-audience', // Неверная аудитория
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_replay_attack_with_used_jti_returns_401()
    {
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

        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'email' => 'test@example.com',
            'jti' => 'reused-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        // Мокируем проверку JTI - возвращаем true, как будто токен уже использовался
        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with('reused-jti', 'test_provider')
            ->andReturn(true);

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }
}
