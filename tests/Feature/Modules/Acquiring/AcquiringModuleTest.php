<?php

namespace Tests\Feature\Modules\Acquiring;

use App\Enums\Role;
use App\Models\AcquirerConfig;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\Modules\Acquiring\Enums\PaymentStatus;
use App\Modules\Acquiring\Services\AcquirerFactory;
use App\Modules\Acquiring\Services\EncryptionService;
use App\Modules\Acquiring\Services\PaymentService;
use App\Modules\Acquiring\Services\TinkoffAcquirer;
use App\Modules\Acquiring\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AcquiringModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $partner;
    protected array $validPartnerCredentials;
    protected array $validPaymentData;
    protected string $testAcquirerPaymentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partner = User::factory()->create([
            'role_id' => Role::partner->value,
            'is_active' => true
        ]);

        $this->validPartnerCredentials = [
            'terminal_key' => 'TestMerchant',
            'secret_key' => 'password',
            'password' => 'password',
        ];

        $this->validPaymentData = [
            'amount' => 100.50,
            'order_id' => 'test_order_' . uniqid(),
            'description' => 'Test payment for unit test',
        ];

        $this->testAcquirerPaymentId = '234567890123';
    }

    public function test_payment_service_can_create_payment_successfully()
    {
        $acquirerConfig = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Init' => Http::response([
                'Success' => true,
                'PaymentId' => $this->testAcquirerPaymentId,
                'PaymentURL' => 'https://securepay.tbank.ru/redirect/abc123',
                'ErrorCode' => 0,
                'Message' => 'OK',
                'Details' => null,
            ], 200),
        ]);

        $paymentService = new PaymentService(app(AcquirerFactory::class));
        $result = $paymentService->createPayment($this->partner, $this->validPaymentData, AcquirerType::TINKOFF);
        $this->assertEquals('Success', $result['status']);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertNotNull($result['redirect_url']);
        $this->assertFalse($result['is_3ds']);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->partner->id,
            'amount' => $this->validPaymentData['amount'],
            'order_id' => $this->validPaymentData['order_id'],
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'status' => PaymentStatus::PENDING->value,
        ]);
    }

    public function test_payment_service_handles_tinkoff_api_error_on_create()
    {
        $acquirerConfig = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Init' => Http::response([
                'Success' => false,
                'ErrorCode' => '1001',
                'Message' => 'Invalid amount',
                'Details' => 'Amount must be greater than zero',
            ], 200),
        ]);

        $paymentService = new PaymentService(app(AcquirerFactory::class));

        $result = $paymentService->createPayment($this->partner, $this->validPaymentData, AcquirerType::TINKOFF);

        $this->assertEquals('Error', $result['status']);
        $this->assertArrayHasKey('error_message', $result);
        $this->assertStringContainsString('Invalid amount', $result['error_message']);
        $this->assertArrayHasKey('error_code', $result);
        $this->assertEquals('1001', $result['error_code']);
    }

    public function test_payment_service_handles_idempotency()
    {
        $acquirerConfig = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        $idempotencyKey = 'idem-key-' . uniqid();
        $existingPaymentData = [
            'user_id' => $this->partner->id,
            'amount' => $this->validPaymentData['amount'],
            'order_id' => $this->validPaymentData['order_id'],
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'idempotency_key' => $idempotencyKey,
            'status' => PaymentStatus::PENDING,
            'metadata' => ['redirect_url' => 'https://old.url']
        ];

        $existingPayment = Payment::create($existingPaymentData);

        $paymentDataWithKey = array_merge($this->validPaymentData, ['idempotency_key' => $idempotencyKey]);

        Http::fake();

        $paymentService = new PaymentService(app(AcquirerFactory::class));
        $result = $paymentService->createPayment($this->partner, $paymentDataWithKey, AcquirerType::TINKOFF);

        $this->assertEquals('Success', $result['status']);
        $this->assertEquals($existingPayment->id, $result['payment']->id);
        Http::assertNothingSent();
    }

    public function test_payment_service_refund_payment_successfully()
    {
        $acquirerConfig = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        $paymentData = [
            'user_id' => $this->partner->id,
            'amount' => 100.00,
            'status' => PaymentStatus::SUCCESS,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'order_id' => 'test_order_refund',
        ];

        $payment = Payment::create($paymentData);

        Http::fake([
            'https://rest-api-test.tinkoff.ru/v2/Cancel' => Http::response([
                'Success' => true,
                'ErrorCode' => 0,
                'Message' => 'OK',
                'Details' => null,
            ], 200),
        ]);

        $paymentService = new PaymentService(app(AcquirerFactory::class));
        $result = $paymentService->refundPayment($payment, 50.00);
        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://rest-api-test.tinkoff.ru/v2/Cancel' &&
                $request['Amount'] == 5000;
        });
    }

    public function test_tinkoff_acquirer_generates_correct_token()
    {
        $acquirer = new TinkoffAcquirer();
        $params = [
            'TerminalKey' => 'TestMerchant',
            'Amount' => '10000',
            'OrderId' => 'TokenTestOrder',
            'Description' => 'Test description',
            'Password' => 'password',
        ];

        $secretKey = 'password';
        $expectedToken = '3083e195b624885ece5f5161b487f68d184e8924778e56eefb0af28678714fed';
        $reflection = new \ReflectionClass($acquirer);
        $method = $reflection->getMethod('generateToken');
        $method->setAccessible(true);
        $generatedToken = $method->invokeArgs($acquirer, [$params, $secretKey]);
        $this->assertEquals($expectedToken, $generatedToken, "Generated token does not match expected. Check TinkoffAcquirer::generateToken logic or test data.");
    }

    public function test_tinkoff_acquirer_handles_webhook_valid_signature()
    {
        $payload = [
            'TerminalKey' => 'TestMerchant',
            'PaymentId' => $this->testAcquirerPaymentId,
            'Status' => 'CONFIRMED',
            'OrderId' => 'ord-123',
            'Password' => 'password',
        ];

        $acquirer = new TinkoffAcquirer();
        $reflection = new \ReflectionClass($acquirer);
        $method = $reflection->getMethod('generateToken');
        $method->setAccessible(true);
        $validToken = $method->invokeArgs($acquirer, [array_diff_key($payload, ['Token' => '']), $this->validPartnerCredentials['secret_key']]);
        $payload['Token'] = $validToken;

        $paymentData = [
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'status' => PaymentStatus::PENDING,
            'user_id' => $this->partner->id,
            'order_id' => 'ord-123',
            'amount' => 100.00,
        ];
        $payment = Payment::create($paymentData);
        $acquirer->handleWebhook($payload, $this->validPartnerCredentials);
        $payment->refresh();
        $this->assertEquals(PaymentStatus::SUCCESS, $payment->status);
    }

    public function test_tinkoff_acquirer_rejects_webhook_invalid_signature()
    {
        $payload = [
            'TerminalKey' => 'TestMerchant',
            'PaymentId' => $this->testAcquirerPaymentId,
            'Status' => 'CONFIRMED',
            'OrderId' => 'ord-123',
            'Token' => 'INVALID_TOKEN_HERE'
        ];

        $acquirer = new TinkoffAcquirer();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Tinkoff webhook signature');
        $acquirer->handleWebhook($payload, $this->validPartnerCredentials);
    }

    public function test_webhook_service_processes_valid_webhook()
    {
        Cache::flush();

        $payload = [
            'TerminalKey' => 'TestMerchant',
            'PaymentId' => $this->testAcquirerPaymentId,
            'Status' => 'CONFIRMED',
            'OrderId' => 'ord-123',
            'Password' => 'password',
        ];

        $acquirer = new TinkoffAcquirer();
        $reflection = new \ReflectionClass($acquirer);
        $method = $reflection->getMethod('generateToken');
        $method->setAccessible(true);
        $validToken = $method->invokeArgs($acquirer, [array_diff_key($payload, ['Token' => '']), $this->validPartnerCredentials['secret_key']]);
        $payload['Token'] = $validToken;

        $paymentData = [
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'user_id' => $this->partner->id,
            'status' => PaymentStatus::PENDING,
            'order_id' => 'ord-123',
            'amount' => 100.00,
        ];
        $payment = Payment::create($paymentData);

        $acquirerConfig = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        $webhookService = new WebhookService(app(AcquirerFactory::class));
        $result = $webhookService->processWebhook($payload, 'tinkoff');

        $this->assertEquals('processed', $result['status']);
        $payment->refresh();
        $this->assertEquals(PaymentStatus::SUCCESS, $payment->status);
    }

    public function test_webhook_service_ignores_duplicate_webhook()
    {
        Cache::flush();

        $payload = [
            'TerminalKey' => 'TestMerchant',
            'PaymentId' => $this->testAcquirerPaymentId,
            'Status' => 'CONFIRMED',
            'OrderId' => 'ord-123',
            'Password' => 'password',
        ];

        $acquirer = new TinkoffAcquirer();
        $reflection = new \ReflectionClass($acquirer);
        $method = $reflection->getMethod('generateToken');
        $method->setAccessible(true);
        $validToken = $method->invokeArgs($acquirer, [array_diff_key($payload, ['Token' => '']), $this->validPartnerCredentials['secret_key']]);
        $payload['Token'] = $validToken;

        $paymentData = [
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'user_id' => $this->partner->id,
            'order_id' => 'ord-123',
            'amount' => 100.00,
            'status' => PaymentStatus::PENDING,
        ];

        $payment = Payment::create($paymentData);

        $acquirerConfig = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        $webhookService = new WebhookService(app(AcquirerFactory::class));

        $result1 = $webhookService->processWebhook($payload, 'tinkoff');
        $result2 = $webhookService->processWebhook($payload, 'tinkoff');
        $this->assertEquals('processed', $result1['status']); // Первый успешен
        $this->assertEquals('duplicate', $result2['status']); // Второй - дубликат
    }


    public function test_encryption_service_encrypts_and_decrypts_data()
    {
        $originalData = json_encode(['terminal_key' => 'tk_test', 'secret_key' => 'sk_test']);
        $encryptionService = new EncryptionService();

        $encryptedData = $encryptionService->encrypt($originalData);
        $decryptedData = $encryptionService->decrypt($encryptedData);

        $this->assertNotEquals($originalData, $encryptedData);
        $this->assertEquals($originalData, $decryptedData);
    }

    public function test_encryption_service_handles_empty_data()
    {
        $encryptionService = new EncryptionService();

        $encryptedEmpty = $encryptionService->encrypt('');
        $decryptedEmpty = $encryptionService->decrypt($encryptedEmpty);

        $this->assertEquals('', $encryptedEmpty);
        $this->assertEquals('', $decryptedEmpty);
    }

    public function test_acquirer_config_encrypts_credentials_on_set()
    {
        $credentials = ['terminal_key' => 'test_tk', 'secret_key' => 'test_sk'];
        $config = new AcquirerConfig([
            'user_id' => $this->partner->id,
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
        ]);

        $config->setCredentials($credentials);
        $config->save();

        $this->assertDatabaseHas('acquirer_configs', [
            'id' => $config->id,
        ]);

        $this->assertNotNull($config->encrypted_credentials);
        $this->assertEquals($credentials, $config->getDecryptedCredentials());
    }

    public function test_acquirer_config_decrypts_credentials_on_get()
    {
        $credentials = ['terminal_key' => 'test_tk', 'secret_key' => 'test_sk'];
        $config = new AcquirerConfig([
            'user_id' => $this->partner->id,
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
        ]);
        $config->setCredentials($credentials);
        $config->save();

        $retrievedConfig = AcquirerConfig::find($config->id);
        $decryptedCredentials = $retrievedConfig->getDecryptedCredentials();
        $this->assertEquals($credentials, $decryptedCredentials);
    }

    public function test_user_model_scope_active_acquirer_config()
    {
        $configData = [
            'user_id' => $this->partner->id,
            'type' => AcquirerType::TINKOFF,
            'is_active' => false,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ];
        $inactiveConfig = AcquirerConfig::create($configData);
        $foundConfig = $this->partner->activeAcquirerConfig(AcquirerType::TINKOFF);
        $this->assertNull($foundConfig);
        $inactiveConfig->update(['is_active' => true]);
        $foundConfig = $this->partner->activeAcquirerConfig(AcquirerType::TINKOFF);
        $this->assertNotNull($foundConfig);
        $this->assertEquals($inactiveConfig->id, $foundConfig->id);
        $this->assertTrue($foundConfig->is_active);
    }

    public function test_acquirer_config_belongs_to_user()
    {
        $config = $this->partner->acquirerConfigs()->create([
            'type' => AcquirerType::TINKOFF,
            'is_active' => true,
            'encrypted_credentials' => $this->validPartnerCredentials,
        ]);

        $relatedUser = $config->user;

        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($this->partner->id, $relatedUser->id);
    }

    public function test_payment_belongs_to_user()
    {
        $paymentData = [
            'user_id' => $this->partner->id,
            'amount' => 100.00,
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'order_id' => 'test_order_rel',
        ];

        $payment = Payment::create($paymentData);
        $relatedUser = $payment->user;
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($this->partner->id, $relatedUser->id);
    }

    public function test_payment_scope_by_idempotency_key()
    {
        $key = 'test-idem-key-' . uniqid();
        $paymentData1 = [
            'user_id' => $this->partner->id,
            'amount' => 100.00,
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'order_id' => 'test_order_idem1',
            'idempotency_key' => $key,
        ];
        $paymentData2 = [
            'user_id' => $this->partner->id,
            'amount' => 200.00,
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'order_id' => 'test_order_idem2',
            'idempotency_key' => 'different-key',
        ];
        $payment1 = Payment::create($paymentData1);
        $payment2 = Payment::create($paymentData2);

        $foundPayments = Payment::query()->byIdempotencyKey($key)->get();

        $this->assertCount(1, $foundPayments);
        $this->assertEquals($payment1->id, $foundPayments->first()->id);
    }

    public function test_payment_scope_by_acquirer_reference()
    {
        $paymentData1 = [
            'user_id' => $this->partner->id,
            'amount' => 100.00,
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => AcquirerType::TINKOFF->value,
            'acquirer_payment_id' => $this->testAcquirerPaymentId,
            'order_id' => 'test_order_ref1',
        ];
        $paymentData2 = [
            'user_id' => $this->partner->id,
            'amount' => 200.00,
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => AcquirerType::TINKOFF->value, // Same type
            'acquirer_payment_id' => 'different_payment_id',
            'order_id' => 'test_order_ref2',
        ];
        $paymentData3 = [
            'user_id' => $this->partner->id,
            'amount' => 300.00,
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => 'different_type', // Different type
            'acquirer_payment_id' => $this->testAcquirerPaymentId, // Same ID
            'order_id' => 'test_order_ref3',
        ];
        $payment1 = Payment::create($paymentData1);
        $payment2 = Payment::create($paymentData2);
        $payment3 = Payment::create($paymentData3);

        $foundPayments = Payment::query()->byAcquirerReference($this->testAcquirerPaymentId, AcquirerType::TINKOFF->value)->get();

        $this->assertCount(1, $foundPayments);
        $this->assertEquals($payment1->id, $foundPayments->first()->id);
    }

}
