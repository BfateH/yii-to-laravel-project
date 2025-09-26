<?php

namespace App\Http\Controllers;

use App\Modules\Acquiring\Services\WebhookService;
use App\Services\TelegramWebhookService;
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

    public function handleTelegramAlert(Request $request, TelegramWebhookService $telegramWebhookService): Response
    {
        $webhook_secret = config('services.telegram.webhook_secret');
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secretToken !== $webhook_secret) {
            Log::warning('Webhook request with invalid secret token: ' . ($secretToken ?? 'NULL'));
            return response('OK', 200);
        }

        $payload = $request->all();

        Log::debug('Webhook payload', ['payload' => $payload]);

        $result = $telegramWebhookService->handle($payload);

        switch ($result['status']) {
            case 'processed':
                Log::info("TelegramWebhookController: Telegram alert webhook processed successfully.");
                return response('OK', 200);

            case 'ignored':
                Log::info("TelegramWebhookController: Telegram alert webhook ignored.", [
                    'reason' => $result['message'] ?? 'No message'
                ]);
                return response('OK', 200);
            case 'error':
            default:
                $message = $result['message'] ?? 'Unknown error';
                Log::error("TelegramWebhookController: Telegram alert webhook processing failed.", [
                    'error_message' => $message
                ]);
                return response('OK', 200);
        }
    }

    public function setTelegramWebhook(Request $request, TelegramWebhookService $telegramWebhookService): \Illuminate\Http\JsonResponse
    {
        $webhookUrl = route('webhook.alerts.telegram');
        $secretToken = config('services.telegram.webhook_secret');
        $result = $telegramWebhookService->setWebhook($webhookUrl, $secretToken);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Вебхук успешно установлен!',
                'telegram_api_result' => $result['result']
            ], 200);
        } else {
            $errorMessage = "Ошибка при установке вебхука: " . $result['message'];
            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
    }
}
