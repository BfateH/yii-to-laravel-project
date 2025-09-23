<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class AlertWebhookService
{
    public function processTelegramWebhook(array $payload): array
    {
        try {
            Log::info('AlertWebhookService: Telegram webhook received', $payload);

            if (isset($payload['message'])) {
                $result = $this->handleTelegramMessage($payload['message']);
            } elseif (isset($payload['callback_query'])) {
                $result = $this->handleTelegramCallback($payload['callback_query']);
            } else {
                $result = ['status' => 'ignored', 'message' => 'Unknown event type'];
            }

            return array_merge($result, ['processed_at' => now()]);

        } catch (\Exception $e) {
            Log::error('AlertWebhookService: Telegram webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    protected function handleTelegramMessage(array $message): array
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';
        $telegramUserId = $message['from']['id'] ?? null;

        if (!$chatId) {
            return [
                'status' => 'ignored',
                'message' => 'Missing chat_id'
            ];
        }

        $existingUser = User::where('telegram_id', $chatId)->first();

        if ($existingUser) {

            Log::info('AlertWebhookService: Telegram user already linked', [
                'system_user_id' => $existingUser->id,
                'chat_id' => $chatId
            ]);

            $this->sendTelegramMessage($chatId, "✅ Ваш Telegram уже привязан к аккаунту {$existingUser->email}");

            return [
                'status' => 'processed',
                'chat_id' => $chatId,
                'message' => 'User already linked'
            ];
        }

        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $text)->first();

            if ($user) {
                if(!$user->telegram_id) {
                    $user->update(['telegram_id' => $chatId]);

                    Log::info('AlertWebhookService: Telegram user linked by email', [
                        'system_user_id' => $user->id,
                        'chat_id' => $chatId,
                        'email' => $text
                    ]);

                    $this->sendTelegramMessage($chatId, "✅ Ваш Telegram успешно привязан к аккаунту {$user->email}!");
                } else {
                    if($user->telegram_id === $chatId) {
                        $this->sendTelegramMessage($chatId, "✅ Ваш Telegram успешно привязан к аккаунту {$user->email}!");
                    } else {
                        $this->sendTelegramMessage($chatId, "✅ Ваш Telegram уже был когда-то успешно привязан к аккаунту {$user->email}! Уведомления будут приходить на ранее привязанный аккаунт телеграмм.");
                    }
                }

                return [
                    'status' => 'processed',
                    'chat_id' => $chatId,
                    'system_user_id' => $user->id,
                    'action' => 'linked_by_email'
                ];
            } else {
                Log::warning('AlertWebhookService: Email not found in system', [
                    'chat_id' => $chatId,
                    'email' => $text
                ]);

                $this->sendTelegramMessage($chatId, "❌ Пользователь с email {$text} не найден в нашей системе. Проверьте email и попробуйте снова.");

                return [
                    'status' => 'error',
                    'chat_id' => $chatId,
                    'message' => 'Email not found'
                ];
            }
        } else {
            Log::info('AlertWebhookService: Prompting user to send email', [
                'chat_id' => $chatId,
                'received_text' => $text
            ]);

            $instruction = "Привет! Для подключения уведомлений отправьте ваш email, который вы используете на нашем сайте.\n\nПример: user@example.com";
            $this->sendTelegramMessage($chatId, $instruction);

            return [
                'status' => 'processed',
                'chat_id' => $chatId,
                'action' => 'prompt_sent'
            ];
        }
    }

    protected function handleTelegramCallback(array $callback): array
    {
        Log::info('AlertWebhookService: Telegram callback received (not implemented)', $callback);

        return [
            'status' => 'ignored',
            'message' => 'Callback handling not implemented'
        ];
    }

    protected function sendTelegramMessage(string $chatId, string $text): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $apiUrl = 'https://api.telegram.org/bot' . $botToken;

            Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl . '/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Exception $e) {
            Log::error('AlertWebhookService: Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
