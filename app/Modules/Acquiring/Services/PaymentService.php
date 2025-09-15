<?php

namespace App\Modules\Acquiring\Services;

use App\Models\Payment;
use App\Models\User;
use App\Modules\Acquiring\Contracts\AcquirerInterface;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\Modules\Acquiring\Enums\PaymentStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    private AcquirerFactory $acquirerFactory;
    public function __construct(AcquirerFactory $acquirerFactory)
    {
        $this->acquirerFactory = $acquirerFactory;
    }

    /**
     * Создать новый платёж.
     *
     * @param User $partner Партнёр (модель User с ролью партнера).
     * @param array $paymentData Данные для создания платежа:
     *                           - 'amount' (float): Сумма.
     *                           - 'order_id' (string): Уникальный ID заказа партнера.
     *                           - 'description' (string, optional): Описание.
     *                           - 'currency' (string, optional): Валюта (по умолчанию 'RUB').
     *                           - 'idempotency_key' (string, optional): Ключ идемпотентности.
     *                           - ... другие данные, специфичные для эквайринга.
     * @param AcquirerType $acquirerType Тип эквайринга (например, AcquirerType::TINKOFF).
     * @return array Результат операции:
     *               - 'status' (string): 'Success' или 'Error'.
     *               - 'payment' (Payment|null): Модель созданного платежа (если успешно).
     *               - 'redirect_url' (string|null): URL для редиректа клиента (если успешно).
     *               - 'is_3ds' (bool): Требуется ли 3D-Secure (если успешно).
     *               - 'error_message' (string, optional): Сообщение об ошибке (если status = 'Error').
     *               - ... другие данные из ответа эквайринга.
     * @throws \Exception
     */
    public function createPayment(User $partner, array $paymentData, AcquirerType $acquirerType): array
    {
        // 1. Получить активную конфигурацию эквайринга для партнера
        $acquirerConfig = $partner->activeAcquirerConfig($acquirerType);

        if (!$acquirerConfig || !$acquirerConfig->is_active) {
            Log::error("PaymentService: No active acquirer config found for partner {$partner->id} and type {$acquirerType->value}.");
            return [
                'status' => 'Error',
                'error_message' => 'No active payment configuration found for this partner and acquirer.',
            ];
        }

        // 2. Расшифровать учетные данные
        $decryptedCredentials = $acquirerConfig->getDecryptedCredentials();
        if (!$decryptedCredentials) {
            Log::error("PaymentService: Failed to decrypt credentials for acquirer config {$acquirerConfig->id}.");
            return [
                'status' => 'Error',
                'error_message' => 'Failed to access payment provider credentials.',
            ];
        }

        // 3. Получить экземпляр эквайринга через фабрику
        $acquirer = $this->acquirerFactory->make($acquirerType);

        // 4. Обработка ключа идемпотентности
        $idempotencyKey = $paymentData['idempotency_key'] ?? null;
        if (!$idempotencyKey) {
            $idempotencyKey = Str::uuid()->toString();
        } else {
            // Проверка на дубликат по ключу идемпотентности
            $existingPayment = Payment::query()->byIdempotencyKey($idempotencyKey)->first();
            if ($existingPayment) {
                Log::info("PaymentService: Payment creation request is idempotent. Returning existing payment {$existingPayment->id}.");

                return [
                    'status' => 'Success',
                    'payment' => $existingPayment,
                    'redirect_url' => $existingPayment->metadata['redirect_url'] ?? null,
                    'is_3ds' => $existingPayment->metadata['is_3ds'] ?? false,
                ];
            }
        }

        // 5. Подготовить данные для эквайринга
        $acquirerPaymentData = array_merge($paymentData, [
            'order_id' => $paymentData['order_id'] ?? uniqid('order_', true),
        ]);

        // 6. Вызвать метод создания платежа у эквайринга
        try {
            $acquirerResponse = $acquirer->createPayment($acquirerPaymentData, $decryptedCredentials);
        } catch (\Exception $e) {
            Log::error("PaymentService: Acquirer createPayment failed.", [
                'exception' => $e->getMessage(),
                'user_id' => $partner->id,
                'acquirer_type' => $acquirerType->value,
                'order_id' => $paymentData['order_id'] ?? 'N/A'
            ]);

            return [
                'status' => 'Error',
                'error_message' => 'Failed to initiate payment with the acquirer: ' . $e->getMessage(),
            ];
        }

        // 7. Обработать ответ от эквайринга
        if (($acquirerResponse['status'] ?? null) !== 'Success') {
            Log::error("PaymentService: Acquirer returned an error.", [
                'acquirer_response' => $acquirerResponse,
                'user_id' => $partner->id,
                'acquirer_type' => $acquirerType->value,
            ]);

            return [
                'status' => 'Error',
                'error_message' => $acquirerResponse['error_message'] ?? 'Payment initiation failed with acquirer.',
                'error_code' => $acquirerResponse['error_code'] ?? null,
                'details' => $acquirerResponse['details'] ?? null,
            ];
        }

        // 8. Создать запись
        $payment = new Payment([
            'user_id' => $partner->id,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'RUB',
            'status' => PaymentStatus::PENDING,
            'acquirer_type' => $acquirerType->value,
            'acquirer_payment_id' => $acquirerResponse['payment_id'] ?? null,
            'order_id' => $paymentData['order_id'] ?? null,
            'description' => $paymentData['description'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'redirect_url' => $acquirerResponse['redirect_url'] ?? null,
                'is_3ds' => $acquirerResponse['is_3ds'] ?? false,
                'acs_url' => $acquirerResponse['acs_url'] ?? null,
                'md' => $acquirerResponse['md'] ?? null,
                'pa_req' => $acquirerResponse['pa_req'] ?? null,
            ],
        ]);

        $payment->save();

        Log::info("PaymentService: Payment created successfully.", [
            'payment_id' => $payment->id,
            'acquirer_payment_id' => $payment->acquirer_payment_id,
            'user_id' => $payment->user_id,
        ]);

        // 9. Вернуть результат
        return [
            'status' => 'Success',
            'payment' => $payment,
            'redirect_url' => $acquirerResponse['redirect_url'] ?? null,
            'is_3ds' => $acquirerResponse['is_3ds'] ?? false,
            'acs_url' => $acquirerResponse['acs_url'] ?? null,
            'md' => $acquirerResponse['md'] ?? null,
            'pa_req' => $acquirerResponse['pa_req'] ?? null,
        ];
    }

    /**
     * Оформить возврат (частичный или полный) по платежу.
     *
     * @param Payment $payment Локальная модель платежа.
     * @param float|null $amount Сумма возврата. Если null, возвращается полная сумма.
     * @return bool True, если возврат успешно инициирован, иначе False.
     * @throws \Exception
     */
    public function refundPayment(Payment $payment, ?float $amount = null): bool
    {
        // 1. Проверка статуса платежа (нельзя возвращать неуспешные или уже возвращенные)
        if (!in_array($payment->status, [PaymentStatus::SUCCESS], true)) {
            Log::warning("PaymentService: Cannot refund payment {$payment->id} with status {$payment->status->value}.");
            return false;
        }

        // 2. Получить партнера и его конфигурацию
        $partner = $payment->user;
        if (!$partner) {
            Log::error("PaymentService: Partner not found for payment {$payment->id}.");
            return false;
        }

        $acquirerType = AcquirerType::tryFrom($payment->acquirer_type);
        if (!$acquirerType) {
            Log::error("PaymentService: Invalid acquirer type '{$payment->acquirer_type}' for payment {$payment->id}.");
            return false;
        }

        $acquirerConfig = $partner->activeAcquirerConfig($acquirerType);
        if (!$acquirerConfig || !$acquirerConfig->is_active) {
            Log::error("PaymentService: No active acquirer config found for partner {$partner->id} and type {$acquirerType->value} for refund.");
            return false;
        }

        // 3. Расшифровать учетные данные
        $decryptedCredentials = $acquirerConfig->getDecryptedCredentials();
        if (!$decryptedCredentials) {
            Log::error("PaymentService: Failed to decrypt credentials for acquirer config {$acquirerConfig->id} for refund.");
            return false;
        }

        // 4. Получить экземпляр эквайринга
        $acquirer = $this->acquirerFactory->make($acquirerType);

        // 5. Вызвать метод возврата у эквайринга
        try {
            $isRefunded = $acquirer->refundPayment($payment, $amount, $decryptedCredentials);
        } catch (\Exception $e) {
            Log::error("PaymentService: Acquirer refundPayment failed.", [
                'exception' => $e->getMessage(),
                'payment_id' => $payment->id,
                'acquirer_type' => $acquirerType->value,
            ]);
            return false;
        }

        // 6. Обновить статус локального платежа, если возврат успешен
        // ВАЖНО: Статус должен обновляться по вебхуку. Здесь можно установить промежуточный статус?
        // if ($isRefunded) {
        //     $payment->status = $amount === null ? PaymentStatus::REFUNDED : PaymentStatus::PARTIALLY_REFUNDED;
        //     $payment->save();
        // }

        return $isRefunded;
    }

    /**
     * Получить статус платежа напрямую из API эквайринга.
     * Полезно для сверки или ручной проверки.
     *
     * @param Payment $payment Локальная модель платежа.
     * @return PaymentStatus|null Внутренний статус или null в случае ошибки.
     * @throws \Exception
     */
    public function getExternalPaymentStatus(Payment $payment): ?PaymentStatus
    {
        // 1. Проверяем наличие внешнего ID
        if (!$payment->acquirer_payment_id) {
            Log::warning("PaymentService: Payment {$payment->id} has no acquirer_payment_id.");
            return null;
        }

        // 2. Получаем партнера и его конфигурацию
        $partner = $payment->user;
        if (!$partner) {
            Log::error("PaymentService: Partner not found for payment {$payment->id} (status check).");
            return null;
        }

        $acquirerType = AcquirerType::tryFrom($payment->acquirer_type);
        if (!$acquirerType) {
            Log::error("PaymentService: Invalid acquirer type '{$payment->acquirer_type}' for payment {$payment->id} (status check).");
            return null;
        }

        $acquirerConfig = $partner->activeAcquirerConfig($acquirerType);
        if (!$acquirerConfig || !$acquirerConfig->is_active) {
            Log::error("PaymentService: No active acquirer config found for partner {$partner->id} and type {$acquirerType->value} (status check).");
            return null;
        }

        // 3. Расшифровываем учетные данные
        $decryptedCredentials = $acquirerConfig->getDecryptedCredentials();
        if (!$decryptedCredentials) {
            Log::error("PaymentService: Failed to decrypt credentials for acquirer config {$acquirerConfig->id} (status check).");
            return null;
        }

        // 4. Получаем экземпляр эквайринга
        /** @var AcquirerInterface $acquirer */
        $acquirer = $this->acquirerFactory->make($acquirerType);

        // 5. Вызываем метод получения статуса у эквайринга
        try {
            $status = $acquirer->getPaymentStatus($payment->acquirer_payment_id, $decryptedCredentials);
        } catch (\Exception $e) {
            Log::error("PaymentService: Acquirer getPaymentStatus failed.", [
                'exception' => $e->getMessage(),
                'payment_id' => $payment->id,
                'acquirer_type' => $acquirerType->value,
            ]);

            return null;
        }

        return $status;
    }
}
