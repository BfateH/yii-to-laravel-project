<?php

namespace App\Modules\Acquiring\Services;

use App\Models\Payment;
use App\Modules\Acquiring\Contracts\AcquirerInterface;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\Modules\Acquiring\Enums\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinkoffAcquirer implements AcquirerInterface
{
    // private string $apiBaseUrl = 'https://securepay.tinkoff.ru'; // Боевая среда
    private string $apiBaseUrl = 'https://rest-api-test.tinkoff.ru'; // Тестовая среда

    public function createPayment(array $data, array $partnerConfig): array
    {
        // 1. Извлечение и проверка обязательных учетных данных партнера
        $terminalKey = $partnerConfig['terminal_key'] ?? null;
        $secretKey = $partnerConfig['secret_key'] ?? null; // Используется для генерации Token
        $password = $partnerConfig['password'] ?? null; // Используется для генерации Token

        if (!$terminalKey || !$secretKey || !$password) {
            Log::error('TinkoffAcquirer: Missing required credentials (terminal_key or secret_key).', ['partner_config_keys' => array_keys($partnerConfig)]);
            throw new \InvalidArgumentException('Missing required Tinkoff credentials (terminal_key or secret_key).');
        }

        // 2. Подготовка данных запроса к API Init
        $requestData = [
            'TerminalKey' => $terminalKey,
            'Amount' => bcmul((string)($data['amount'] ?? 0), '100', 0), // Перевод рублей в копейки
            'OrderId' => $data['order_id'] ?? uniqid('order_', true), // Генерация уникального ID
        ];

        // Необязательные параметры (если они есть в $data)
        $optionalFields = [
            'Description', 'Language', 'Recurrent', 'CustomerKey',
            'RedirectDueDate', 'NotificationURL', 'SuccessURL', 'FailURL',
            'PayType', 'Receipt', 'DATA' // DATA - произвольные данные
        ];
        foreach ($optionalFields as $field) {
            $key = strtolower($field); // Поля в $data могут быть в нижнем регистре
            if (isset($data[$key])) {
                $requestData[$field] = $data[$key];
            }
        }

        $dataForToken = $requestData;
        $dataForToken['Password'] = $password;
        dump($dataForToken);

        // 3. Генерация токена для запроса
        $requestData['Token'] = $this->generateToken($dataForToken, $secretKey);

        // 4. Отправка HTTP-запроса к API Тинькофф
        $url = $this->apiBaseUrl . '/v2/Init';
        Log::debug('TinkoffAcquirer: Sending Init request.', ['url' => $url, 'request_data_keys' => array_keys($requestData)]);
        dump($partnerConfig);
        dump($requestData);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->timeout(30)
            ->post($url, $requestData);

        // 5. Обработка ответа
        if ($response->failed()) {
            Log::error('TinkoffAcquirer: Init API request failed.', [
                'status' => $response->status(),
                'response_body' => $response->body(),
                'request_url' => $url,
                'request_data_sent' => array_diff_key($requestData, ['Token' => '']) // Исключаем Token из лога
            ]);
            throw new \RuntimeException("Tinkoff Init API request failed with status {$response->status()}.");
        }

        $responseData = $response->json();
        dump($responseData);

        // 6. Проверка успешности операции по флагу Success
        if (!isset($responseData['Success']) || $responseData['Success'] !== true) {
            $errorMessage = $responseData['Message'] ?? $responseData['Details'] ?? 'Unknown error from Tinkoff API.';
            Log::error('TinkoffAcquirer: Init API returned an error.', [
                'error_code' => $responseData['ErrorCode'] ?? 'N/A',
                'message' => $errorMessage,
                'request_url' => $url,
                'request_data_sent' => array_diff_key($requestData, ['Token' => ''])
            ]);

            return [
                'status' => 'Error',
                'error_message' => $errorMessage,
                'error_code' => $responseData['ErrorCode'] ?? null,
                'details' => $responseData['Details'] ?? null,
            ];
        }

        // 7. Подготовка успешного результата
        Log::info('TinkoffAcquirer: Payment initiated successfully.', ['payment_id' => $responseData['PaymentId'] ?? 'N/A']);
        return [
            'status' => 'Success',
            'payment_id' => $responseData['PaymentId'] ?? null,
            'redirect_url' => $responseData['PaymentURL'] ?? null,
            // Согласно спецификации, при 3DS будет поле ACSUrl
            'is_3ds' => isset($responseData['ACSUrl']),
            'acs_url' => $responseData['ACSUrl'] ?? null, // URL для редиректа на 3DS
            'md' => $responseData['MD'] ?? null, // MD для 3DS
            'pa_req' => $responseData['PaReq'] ?? null, // PaReq для 3DS
        ];
    }


    public function handleWebhook(array $payload, array $partnerConfig): void
    {
        // 1. Извлечение секретного ключа партнера
        $secretKey = $partnerConfig['secret_key'] ?? null;
        if (!$secretKey) {
            Log::error('TinkoffAcquirer: Missing secret key for webhook validation.');
            throw new \InvalidArgumentException('Missing secret key for Tinkoff webhook validation.');
        }

        // 2. Извлечение токена из уведомления
        $receivedToken = $payload['Token'] ?? null;
        if (!$receivedToken) {
            Log::warning('TinkoffAcquirer: Webhook payload missing Token.', ['payload_keys' => array_keys($payload)]);
            throw new \InvalidArgumentException('Webhook payload missing Token.');
        }

        // 3. Проверка подписи (Token)
        $dataForToken = array_diff_key($payload, array_flip(['Token']));
        $expectedToken = $this->generateToken($dataForToken, $secretKey);

        if (!hash_equals($expectedToken, $receivedToken)) {
            Log::warning('TinkoffAcquirer: Invalid webhook signature.', [
                'received_token_prefix' => substr($receivedToken, 0, 10) . '...',
                'expected_token_prefix' => substr($expectedToken, 0, 10) . '...',
                'payload_structure' => array_keys($payload)
            ]);
            throw new \InvalidArgumentException('Invalid Tinkoff webhook signature.');
        }

        // 4. Извлечение идентификатора платежа и статуса
        $acquirerPaymentId = $payload['PaymentId'] ?? null;
        $status = $payload['Status'] ?? null;

        if (!$acquirerPaymentId || !$status) {
            Log::error('TinkoffAcquirer: Invalid webhook payload structure (missing PaymentId or Status).', ['payload_keys' => array_keys($payload)]);
            throw new \InvalidArgumentException('Invalid Tinkoff webhook payload: missing PaymentId or Status.');
        }

        // 5. Преобразование статуса Тинькофф в наш внутренний статус
        $paymentStatus = match (strtoupper($status)) {
            'AUTHORIZED', 'CONFIRMED' => PaymentStatus::SUCCESS,
            'REJECTED', 'REVERSED' => PaymentStatus::FAILED,
            'REFUNDED' => PaymentStatus::REFUNDED,
            'PARTIAL_REFUNDED' => PaymentStatus::PARTIALLY_REFUNDED,
            'CANCELLED' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };

        // 6. Поиск и обновление локального объекта Payment
        $payment = Payment::query()
            ->where('acquirer_payment_id', $acquirerPaymentId)
            ->where('acquirer_type', AcquirerType::TINKOFF->value)
            ->first();

        if ($payment) {
            $oldStatus = $payment->status;
            $payment->status = $paymentStatus;

            // Обновляем метаданные, если они пришли
            $metadataUpdates = [];
            // CardId и RebillId упомянуты в примерах уведомлений
            if (!empty($payload['CardId'])) {
                $metadataUpdates['card_id'] = $payload['CardId'];
            }
            if (!empty($payload['RebillId'])) {
                $metadataUpdates['rebill_id'] = $payload['RebillId'];
            }

            // PAN НЕ СОХРАНЯЕМ!
            if (!empty($metadataUpdates)) {
                $payment->metadata = array_merge($payment->metadata ?? [], $metadataUpdates);
            }

            $payment->save();
            Log::info("TinkoffAcquirer: Webhook processed. Payment {$payment->id} status updated.", [
                'from' => $oldStatus->value,
                'to' => $paymentStatus->value,
                'acquirer_payment_id' => $acquirerPaymentId
            ]);
        } else {
            Log::warning("TinkoffAcquirer: Webhook received for unknown payment.", [
                'acquirer_payment_id' => $acquirerPaymentId,
                'status' => $status
            ]);
        }
    }

    public function refundPayment(Payment $payment, ?float $amount, array $partnerConfig): bool
    {
        // 1. Проверка наличия необходимых данных
        $terminalKey = $partnerConfig['terminal_key'] ?? null;
        $secretKey = $partnerConfig['secret_key'] ?? null;

        if (!$terminalKey || !$secretKey) {
            Log::error('TinkoffAcquirer: Missing credentials for refund.');
            throw new \InvalidArgumentException('Missing required Tinkoff credentials for refund.');
        }

        if (!$payment->acquirer_payment_id) {
            Log::error('TinkoffAcquirer: Payment has no acquirer_payment_id for refund.', ['payment_id' => $payment->id]);
            throw new \InvalidArgumentException('Payment has no acquirer reference for refund.');
        }

        // 2. Подготовка данных запроса к API Cancel
        $requestData = [
            'TerminalKey' => $terminalKey,
            'PaymentId' => $payment->acquirer_payment_id,
        ];

        // Если указана сумма, добавляем её (в копейках)
        if ($amount !== null) {
            $requestData['Amount'] = bcmul((string)$amount, '100', 0);
        }

        // 3. Генерация токена для запроса возврата
        $requestData['Token'] = $this->generateToken($requestData, $secretKey);

        // 4. Отправка запроса
        $url = $this->apiBaseUrl . '/v2/Cancel';
        Log::debug('TinkoffAcquirer: Sending Cancel (Refund) request.', ['url' => $url, 'payment_id' => $payment->acquirer_payment_id]);
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, $requestData);

        // 5. Обработка ответа
        if ($response->failed()) {
            Log::error('TinkoffAcquirer: Cancel API request failed.', [
                'status' => $response->status(),
                'response_body' => $response->body(),
                'request_url' => $url,
                'payment_id' => $payment->acquirer_payment_id,
                'request_data_sent' => array_diff_key($requestData, ['Token' => ''])
            ]);
            return false;
        }

        $responseData = $response->json();

        // 6. Проверка результата
        if (isset($responseData['Success']) && $responseData['Success'] === true) {
            Log::info("TinkoffAcquirer: Refund initiated successfully for payment {$payment->id}.", ['response' => $responseData]);
            return true;
        } else {
            $errorMessage = $responseData['Message'] ?? $responseData['Details'] ?? 'Unknown error from Tinkoff API.';
            Log::error('TinkoffAcquirer: Cancel API returned an error.', [
                'error_code' => $responseData['ErrorCode'] ?? 'N/A',
                'message' => $errorMessage,
                'request_url' => $url,
                'payment_id' => $payment->acquirer_payment_id,
                'response' => $responseData
            ]);
            return false;
        }
    }

    public function getPaymentStatus(string $acquirerPaymentId, array $partnerConfig): PaymentStatus
    {
        // 1. Проверка учетных данных
        $terminalKey = $partnerConfig['terminal_key'] ?? null;
        $secretKey = $partnerConfig['secret_key'] ?? null;

        if (!$terminalKey || !$secretKey) {
            Log::error('TinkoffAcquirer: Missing credentials for status check.');
            throw new \InvalidArgumentException('Missing required Tinkoff credentials for status check.');
        }

        // 2. Подготовка данных запроса к API GetState
        $requestData = [
            'TerminalKey' => $terminalKey,
            'PaymentId' => $acquirerPaymentId,
        ];

        // 3. Генерация токена
        $requestData['Token'] = $this->generateToken($requestData, $secretKey);

        // 4. Отправка запроса
        $url = $this->apiBaseUrl . '/v2/GetState';
        Log::debug('TinkoffAcquirer: Sending GetState request.', ['url' => $url, 'payment_id' => $acquirerPaymentId]);
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, $requestData);

        // 5. Обработка ответа
        if ($response->failed()) {
            Log::error('TinkoffAcquirer: GetState API request failed.', [
                'status' => $response->status(),
                'response_body' => $response->body(),
                'request_url' => $url,
                'payment_id' => $acquirerPaymentId,
                'request_data_sent' => array_diff_key($requestData, ['Token' => ''])
            ]);
            throw new \RuntimeException("Tinkoff GetState API request failed with status {$response->status()}.");
        }

        $responseData = $response->json();

        // 6. Проверка успешности запроса
        if (!isset($responseData['Success']) || $responseData['Success'] !== true) {
            $errorMessage = $responseData['Message'] ?? $responseData['Details'] ?? 'Unknown error from Tinkoff API.';
            Log::error('TinkoffAcquirer: GetState API returned an error.', [
                'error_code' => $responseData['ErrorCode'] ?? 'N/A',
                'message' => $errorMessage,
                'request_url' => $url,
                'payment_id' => $acquirerPaymentId,
                'response' => $responseData
            ]);
            throw new \RuntimeException("Tinkoff payment status check failed: {$errorMessage}");
        }

        // 7. Преобразование статуса API в наш внутренний статус
        $status = $responseData['Status'] ?? null;
        if (!$status) {
            Log::error('TinkoffAcquirer: GetState API response missing Status.', ['response' => $responseData]);
            throw new \RuntimeException('Tinkoff GetState API response missing Status.');
        }

        return match (strtoupper($status)) {
            'NEW', 'FORM_SHOWED' => PaymentStatus::PENDING,
            'AUTHORIZING', '3DS_CHECKING' => PaymentStatus::PENDING,
            'AUTHORIZED', 'CONFIRMED' => PaymentStatus::SUCCESS,
            'REJECTED', 'REVERSED', 'REJECTED_BLACKLIST', 'DEADLINE_EXPIRED' => PaymentStatus::FAILED,
            'REFUNDED' => PaymentStatus::REFUNDED,
            'PARTIAL_REFUNDED' => PaymentStatus::PARTIALLY_REFUNDED,
            'CANCELLED' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Сгенерировать токен (подпись) для запросов к API Тинькофф.
     * Согласно документации: Token = strtoupper(hash('sha256', implode('', ksort($params)) . $secretKey))
     *
     * @param array $params Массив параметров запроса.
     * @param string $secretKey Секретный ключ терминала.
     * @return string Сгенерированный токен.
     */
    private function generateToken(array $params, string $secretKey): string
    {
        // 1. Сортировка параметров по ключам в алфавитном порядке (рекомендация Тинькофф)
        ksort($params);

        // 2. Конкатенация значений всех параметров
        // ВАЖНО: Используются только значения, ключи не участвуют
        $dataString = implode('', array_values($params));

        // 3. Добавление секретного ключа
        $dataString .= $secretKey;

        // 4. Вычисление SHA-256 хэша и приведение к верхнему регистру
        return strtoupper(hash('sha256', $dataString));
    }
}
