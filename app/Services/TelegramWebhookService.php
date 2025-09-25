<?php

namespace App\Services;

use App\Enums\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class TelegramWebhookService
{
    public function sendTelegramMessage(string $chatId, string $text): void
    {
        try {
            $botToken = config('services.telegram.bot_token') ?? '7551395829:AAF1n3G1ofz8ZNkUWepnsrwktZFNms7dCb0';

            if (!$botToken) {
                Log::error('TelegramWebhookService: BOT token not configured');
                return;
            }

            $apiUrl = 'https://api.telegram.org/bot' . $botToken;

            Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl . '/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Exception $e) {
            Log::error('TelegramWebhookService: Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function handle(array $payload): array
    {
        try {
            if (isset($payload['message'])) {
                return $this->handleMessage($payload['message']);
            }

            if (isset($payload['callback_query'])) {
                return $this->handleCallback($payload['callback_query']);
            }

            return [
                'status' => 'ignored',
                'message' => 'Unknown event type'
            ];

        } catch (\Throwable $e) {
            Log::error('TelegramWebhookService: Exception during processing', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    protected function handleMessage(array $message): array
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'] ?? null;

        if ($chatId === null) {
            return ['status' => 'ignored', 'message' => 'Missing chat.id'];
        }

        if ($chatId > 0) {
            return $this->handlePrivateChat($chatId, $text, $userId);
        }

        // Поддержка партнёров тут
        if (str_starts_with((string) $chatId, '-100')) {
            return $this->handleSupergroup($chatId, $text, $userId, $message);
        }

        return [
            'status' => 'ignored',
            'message' => "Unsupported chat type: {$chatId}"
        ];
    }

    protected function handleCallback(array $callback): array
    {
        Log::info('TelegramWebhookService: Callback received (not implemented)', $callback);

        return [
            'status' => 'ignored',
            'message' => 'Callback not implemented'
        ];
    }

    protected function handlePrivateChat(int $chatId, string $text, ?int $userId): array
    {
        if ($existingUser = User::query()->where('telegram_id', $chatId)->first()) {
            $this->sendTelegramMessage($chatId, "✅ Ваш Telegram уже привязан к аккаунту {$existingUser->email}");

            Log::info('TelegramWebhookService: Telegram already linked', [
                'user_id' => $existingUser->id,
                'chat_id' => $chatId
            ]);

            return [
                'status' => 'processed',
                'chat_id' => $chatId,
                'message' => 'User already linked'
            ];
        }

        if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $this->sendTelegramMessage($chatId, "Привет! Для подключения уведомлений отправьте ваш email, который вы используете на нашем сайте.\n\nПример: user@example.com");
            return [
                'status' => 'processed',
                'chat_id' => $chatId,
                'action' => 'prompt_sent'
            ];
        }

        return $this->linkTelegramByEmailAddress($chatId, $text);
    }

    protected function handleSupergroup(int $chatId, string $text, ?int $userId, array $message): array
    {
        // Находим партнёра, к которому привязана эта группа
        $partner = User::query()
            ->where('telegram_support_chat_id', $chatId)
            ->where('role_id', Role::partner->value)
            ->first();

        if (!$partner) {
            return $this->attemptBindGroupToPartner($chatId, $userId);
        }

        if (!isset($message['reply_to_message'])) {
            return [
                'status' => 'ignored',
                'message' => 'Non-reply messages in support group are ignored'
            ];
        }

        return $this->handleOperatorReply($chatId, $userId, $text, $message['reply_to_message']);
    }

    protected function handleOperatorReply(int $chatId, ?int $userId, string $text, array $replyToMessage): array
    {
        // Заглушка для будущей реализации
        // Здесь будет логика: найти тикет по replyToMessage['message_id'], добавить ответ
        return [
            'status' => 'ignored',
            'message' => 'Operator reply handling not implemented yet'
        ];
    }

    private function attemptBindGroupToPartner(int $chatId, ?int $userId): array
    {
        if (!$userId) {
            return [
                'status' => 'ignored',
                'message' => 'Cannot bind group: sender user ID missing'
            ];
        }

        $user = User::query()
            ->where('telegram_id', $userId)
            ->where('role_id', Role::partner->value)
            ->first();

        if (!$user) {
            $this->sendTelegramMessage($chatId, "❌ Для привязки группы в неё должен написать партнёр.");
            return [
                'status' => 'ignored',
                'message' => 'Sender is not a partner'
            ];
        }

        $user->telegram_support_chat_id = $chatId;
        $user->save();

        $this->sendTelegramMessage($chatId, "✅ Группа успешно привязана к партнёру {$user->email}");

        Log::info('TelegramWebhookService: Group auto-bound to partner', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'email' => $user->email
        ]);

        return [
            'status' => 'processed',
            'action' => 'group_bound_to_partner'
        ];
    }

    private function linkTelegramByEmailAddress(string $chatId, string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            Log::warning('TelegramWebhookService: Email not found', [
                'chat_id' => $chatId,
                'email' => $email
            ]);

            $this->sendTelegramMessage($chatId, "❌ Пользователь с email {$email} не найден в нашей системе.");

            return [
                'status' => 'error',
                'chat_id' => $chatId,
                'message' => 'Email not found'
            ];
        }

        if (!$user->telegram_id) {
            $user->update(['telegram_id' => $chatId]);

            Log::info('TelegramWebhookService: Linked by email', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'email' => $email
            ]);

            $this->sendTelegramMessage($chatId, "✅ Ваш Telegram успешно привязан к аккаунту {$user->email}!");
        } else {
            if ($user->telegram_id === $chatId) {
                $this->sendTelegramMessage($chatId, "✅ Ваш Telegram уже привязан к аккаунту {$user->email}!");
            } else {
                $this->sendTelegramMessage($chatId, "✅ Ваш Telegram уже был привязан к аккаунту {$user->email}. Уведомления приходят на привязанный аккаунт.");
            }
        }

        return [
            'status' => 'processed',
            'chat_id' => $chatId,
            'system_user_id' => $user->id,
            'action' => 'linked_by_email'
        ];
    }
}
