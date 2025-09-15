<?php

namespace App\Http\Controllers;

use App\Modules\Acquiring\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    private WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function handleTinkoff(Request $request): Response
    {
        // 1. Получение данных
        $payload = $request->all();

        // 2. Вызов сервиса обработки
        $result = $this->webhookService->processWebhook($payload, 'tinkoff');

        // 3. Формирование HTTP-ответа в зависимости от результата
        switch ($result['status']) {
            case 'processed':
                // Успешная обработка.
                // Тинькофф ожидает 200 OK в случае успеха.
                Log::info("WebhookController: Tinkoff webhook processed successfully.", ['acquirer_payment_id' => $result['acquirer_payment_id']]);
                return response('OK', 200);

            case 'duplicate':
                // Дубликат. Тоже считается успешно обработанным.
                Log::info("WebhookController: Tinkoff webhook duplicate ignored.", ['acquirer_payment_id' => $result['acquirer_payment_id']]);
                return response('OK', 200);

            case 'error':
            default:
                // Ошибка обработки. Возвращаем 400 или 403.
                $message = $result['message'] ?? 'Webhook processing failed.';
                Log::error("WebhookController: Tinkoff webhook processing failed.", [
                    'acquirer_payment_id' => $result['acquirer_payment_id'],
                    'error_message' => $message
                ]);

                // Если сообщение об ошибке связано с валидацией
                if (Str::contains($message, ['signature', 'validation'])) {
                    return response('Forbidden: ' . $message, 403);
                }

                return response('Bad Request: ' . $message, 400);
        }
    }
}
