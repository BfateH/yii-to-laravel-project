<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JtiService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class SsoJwtAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $jtiServiceMock;
    protected $privateKey;
    protected $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем таблицу moonshine_user_roles если её нет
        if (!Schema::hasTable('moonshine_user_roles')) {
            Schema::create('moonshine_user_roles', function ($table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            // Добавляем роль по умолчанию только при создании таблицы
            DB::table('moonshine_user_roles')->insert([
                'id' => 1,
                'name' => 'User',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Если таблица уже существует, проверяем есть ли роль с id=1
            if (!DB::table('moonshine_user_roles')->where('id', 1)->exists()) {
                DB::table('moonshine_user_roles')->insert([
                    'id' => 1,
                    'name' => 'User',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

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

    public function test_sso_creates_new_user_on_first_login()
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
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'jti' => 'first-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with('first-jti', 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with('first-jti', 'test_provider', \Mockery::type('int'));

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(200);

        // Проверяем, что пользователь создан в базе
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'provider' => 'test_provider',
            'provider_id' => '123',
            'role_id' => 1,
        ]);

        // Проверяем, что пароль установлен
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->password);
        $this->assertNotEmpty($user->password);

        // Проверяем, что ответ содержит токен и данные пользователя
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'email', 'name'],
        ]);
    }

    public function test_sso_logs_in_existing_user_on_subsequent_login()
    {
        // Сначала создаем пользователя вручную
        $user = User::create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'provider' => 'test_provider',
            'provider_id' => '456',
            'password' => Hash::make(uniqid()),
            'role_id' => 1,
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
            'sub' => '456',
            'email' => 'existing@example.com',
            'name' => 'Updated Name', // Измененное имя
            'jti' => 'second-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with('second-jti', 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with('second-jti', 'test_provider', \Mockery::type('int'));

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(200);

        // Проверяем, что пользователь обновил имя
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'name' => 'Updated Name',
        ]);

        // Проверяем, что пользователь не создался заново
        $this->assertDatabaseCount('users', 1);
        $this->assertEquals($user->id, User::where('email', 'existing@example.com')->first()->id);
    }

    public function test_sso_updates_user_data_on_subsequent_login()
    {
        // Сначала создаем пользователя с устаревшими данными
        $user = User::create([
            'email' => 'update@example.com',
            'name' => 'Old Name',
            'provider' => 'test_provider',
            'provider_id' => '789',
            'password' => Hash::make(uniqid()),
            'role_id' => 1,
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
            'sub' => '789',
            'email' => 'update@example.com',
            'name' => 'New Name', // Новое имя
            'jti' => 'update-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with('update-jti', 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with('update-jti', 'test_provider', \Mockery::type('int'));

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(200);

        // Проверяем, что данные пользователя обновились
        $this->assertDatabaseHas('users', [
            'email' => 'update@example.com',
            'name' => 'New Name',
            'provider_id' => '789',
        ]);

        // Проверяем, что пользователь не создался заново
        $this->assertDatabaseCount('users', 1);
    }

    public function test_sso_creates_user_with_provider_id_even_when_missing_in_token()
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
            'email' => 'noid@example.com',
            'name' => 'No ID User',
            'jti' => 'noid-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->with('noid-jti', 'test_provider')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->with('noid-jti', 'test_provider', \Mockery::type('int'));

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(200);

        // Проверяем, что пользователь создан даже без provider_id
        $this->assertDatabaseHas('users', [
            'email' => 'noid@example.com',
            'name' => 'No ID User',
            'provider' => 'test_provider',
            'provider_id' => null,
        ]);
    }

    public function test_sso_fails_when_email_is_missing()
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

        // Создаем payload без email
        $payload = [
            'iss' => 'https://example.com',
            'aud' => 'test-audience',
            'exp' => now()->addHour()->timestamp,
            'iat' => now()->timestamp,
            'sub' => '123',
            'name' => 'User Without Email',
            'jti' => 'no-email-jti',
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');

        $this->jtiServiceMock
            ->shouldReceive('isAlreadyUsed')
            ->andReturn(false);

        $this->jtiServiceMock
            ->shouldReceive('markAsUsed')
            ->withAnyArgs();

        $response = $this->postJson(route('auth.token-login'), [
            'provider' => 'test_provider',
            'token' => $token,
        ]);

        $response->assertStatus(401);

        $response->assertJsonStructure([
            'error',
            'message',
        ]);

        $this->assertDatabaseMissing('users', [
            'name' => 'User Without Email',
            'provider_id' => '123',
        ]);


        $this->assertDatabaseMissing('users', [
            'email' => '',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Очищаем моки
        \Mockery::close();
    }
}
