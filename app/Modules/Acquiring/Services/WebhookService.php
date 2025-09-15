<?php

namespace App\Modules\Acquiring\Services;

use App\Models\Payment;
use App\Modules\Acquiring\Contracts\AcquirerInterface;
use App\Modules\Acquiring\Enums\AcquirerType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    private AcquirerFactory $acquirerFactory;
    public function __construct(AcquirerFactory $acquirerFactory)
    {
        $this->acquirerFactory = $acquirerFactory;
    }

    /**
     * Обработать входящий вебхук.
     *
     * @param array $payload Данные из тела POST-запроса.
     * @param string $acquirerTypeValue Строковое значение типа эквайринга (например, 'tinkoff').
     * @return array Результат обработки:
     *               - 'status' (string): 'processed', 'duplicate', 'error'.
     *               - 'message' (string): Описание результата.
     *               - 'payment_id' (int|null): ID локального платежа, если найден/обработан.
     *               - 'acquirer_payment_id' (string|null): ID платежа у эквайринга.
     */
    public function processWebhook(array $payload, string $acquirerTypeValue): array
    {
        $logContext = ['acquirer_type' => $acquirerTypeValue, 'payload_keys' => array_keys($payload)];

        // 1. Определение типа эквайринга
        $acquirerType = AcquirerType::tryFrom($acquirerTypeValue);
        if (!$acquirerType) {
            Log::warning("WebhookService: Unknown acquirer type received.", $logContext);
            return [
                'status' => 'error',
                'message' => 'Unknown acquirer type.',
                'acquirer_payment_id' => $payload['PaymentId'] ?? null,
            ];
        }

        // 2. Идентификация уведомления для дедупликации
        $acquirerPaymentId = $payload['PaymentId'] ?? null;
        $status = $payload['Status'] ?? null;
        $idempotencyKey = $payload['OrderId'] ?? null;

        if (!$acquirerPaymentId || !$status) {
            Log::error("WebhookService: Invalid payload structure for deduplication.", $logContext);
            return [
                'status' => 'error',
                'message' => 'Invalid payload structure for deduplication (missing PaymentId or Status).',
                'acquirer_payment_id' => $acquirerPaymentId,
            ];
        }

        // Ключ дедупликации
        $deduplicationKey = md5("{$acquirerPaymentId}:{$status}");

        // 3. Проверка дедупликации (упрощенная) потом надно реализовать через базу данных
        if (cache()->has($deduplicationKey)) {
            Log::info("WebhookService: Duplicate webhook received and ignored.", array_merge($logContext, ['dedup_key' => $deduplicationKey]));
            return [
                'status' => 'duplicate',
                'message' => 'Webhook already processed.',
                'acquirer_payment_id' => $acquirerPaymentId,
            ];
        }

        // 4. Поиск локального платежа для получения партнера и его конфигурации
        $payment = Payment::query()
            ->byAcquirerReference($acquirerPaymentId, $acquirerType->value)
            ->first();

        if (!$payment) {
            Log::warning("WebhookService: Payment not found for acquirer reference.", array_merge($logContext, ['acquirer_payment_id' => $acquirerPaymentId]));

            return [
                 'status' => 'error',
                 'message' => 'Local payment not found.',
                 'acquirer_payment_id' => $acquirerPaymentId,
             ];
        }

        // 5. Получение конфигурации эквайринга партнера
        $acquirerConfig = null;
        if ($payment->user) {
            $acquirerConfig = $payment->user->activeAcquirerConfig($acquirerType);
        }

        if (!$acquirerConfig || !$acquirerConfig->is_active) {
            $message = "WebhookService: No active acquirer config found for partner of payment {$payment->id}.";
            Log::error($message, $logContext);

            // Без конфигурации мы не можем проверить подпись. Лучше отклонить.
            // Но пока сохраним дедупликацию.
            cache()->put($deduplicationKey, true, now()->addMinutes(10));
            return [
                'status' => 'error',
                'message' => 'Unable to validate webhook: acquirer config missing or inactive.',
                'acquirer_payment_id' => $acquirerPaymentId,
            ];
        }

        // 6. Расшифровка учетных данных партнера
        $decryptedCredentials = $acquirerConfig->getDecryptedCredentials();
        if (!$decryptedCredentials) {
            Log::error("WebhookService: Failed to decrypt credentials for acquirer config {$acquirerConfig->id}.", $logContext);
            cache()->put($deduplicationKey, true, now()->addMinutes(10));
            return [
                'status' => 'error',
                'message' => 'Failed to access acquirer credentials for signature validation.',
                'acquirer_payment_id' => $acquirerPaymentId,
            ];
        }

        // 7. Получение экземпляра эквайринга
        $acquirer = $this->acquirerFactory->make($acquirerType);

        // 8. Делегирование обработки вебхука эквайрингу
        // Эквайринг сам проверит подпись и обновит статус платежа
        try {
            // Передаем payload и расшифрованные учетные данные
            $acquirer->handleWebhook($payload, $decryptedCredentials);
        } catch (\InvalidArgumentException $e) {
            Log::warning("WebhookService: Webhook validation failed.", array_merge($logContext, ['exception' => $e->getMessage()]));
            cache()->put($deduplicationKey, true, now()->addMinutes(10)); // Считаем обработанным
            return [
                'status' => 'error',
                'message' => 'Webhook validation failed: ' . $e->getMessage(),
                'acquirer_payment_id' => $acquirerPaymentId,
            ];
        } catch (\Exception $e) {
            Log::error("WebhookService: Acquirer failed to handle webhook.", array_merge($logContext, ['exception' => $e->getMessage()]));

            // Не сохраняем дедупликацию, чтобы можно было повторить обработку
            return [
                'status' => 'error',
                'message' => 'Acquirer failed to process webhook: ' . $e->getMessage(),
                'acquirer_payment_id' => $acquirerPaymentId,
            ];
        }

        // 9. Если мы дошли до этой точки, вебхук обработан успешно
        // Сохраняем дедупликацию
        cache()->put($deduplicationKey, true, now()->addMinutes(10));

        Log::info("WebhookService: Webhook processed successfully.", array_merge($logContext, [
            'acquirer_payment_id' => $acquirerPaymentId,
            'local_payment_id' => $payment->id ?? null
        ]));

        return [
            'status' => 'processed',
            'message' => 'Webhook processed successfully.',
            'payment_id' => $payment->id ?? null,
            'acquirer_payment_id' => $acquirerPaymentId,
        ];
    }
}
