<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Ticket;
use App\Modules\SupportChat\Services\MessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class TelegramWebhookService
{
    public function setWebhook(string $webhookUrl, ?string $secretToken = null): array
    {
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            Log::error('TelegramWebhookService: BOT token not configured for setWebhook');
            return [
                'success' => false,
                'message' => 'Bot token is not configured.'
            ];
        }

        $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/setWebhook';
        $params = [
            'url' => $webhookUrl,
        ];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        $response = Http::timeout(30)->post($apiUrl, $params);

        if (!$response->successful()) {
            Log::error('TelegramWebhookService: Failed to set webhook', [
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);
            return [
                'success' => false,
                'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()
            ];
        }

        $responseData = $response->json();
        if (!isset($responseData['ok']) || !$responseData['ok']) {
            Log::error('TelegramWebhookService: Telegram API error setting webhook', [
                'api_response' => $responseData
            ]);
            return [
                'success' => false,
                'message' => 'Telegram API Error: ' . ($responseData['description'] ?? 'Unknown error')
            ];
        }

        Log::info('TelegramWebhookService: Webhook set successfully', [
            'webhook_url' => $webhookUrl,
            'has_secret_token' => (bool)$secretToken
        ]);

        return [
            'success' => true,
            'message' => 'Webhook set successfully.',
            'result' => $responseData['result'] ?? null
        ];
    }

    public function sendTelegramMessage(string $chatId, string $text): void
    {
        try {
            $botToken = config('services.telegram.bot_token');

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
                return $this->handleMessage($payload['message'], $payload);
            }

            if (isset($payload['callback_query'])) {
                return $this->handleCallback($payload['callback_query'], $payload);
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

    protected function handleMessage(array $message, $payload): array
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
        if (str_starts_with((string)$chatId, '-100')) {
            return $this->handleSupergroup($chatId, $text, $userId, $message, $payload);
        }

        return [
            'status' => 'ignored',
            'message' => "Unsupported chat type: {$chatId}"
        ];
    }

    protected function handleCallback(array $callback, $payload): array
    {
        Log::info('TelegramWebhookService: Callback received (not implemented)', $callback);

        return [
            'status' => 'ignored',
            'message' => 'Callback not implemented'
        ];
    }

    protected function handlePrivateChat(int $chatId, string $text, ?int $userId): array
    {
        if ($text === 'remove_id') {
            User::query()->update(['telegram_id' => null]);
        }

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

    protected function handleSupergroup(int $chatId, string $text, ?int $userId, array $message, $payload): array
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

        return $this->handleOperatorReply($chatId, $userId, $text, $message['reply_to_message'], $payload);
    }

    protected function handleOperatorReply(int $chatId, ?int $userId, string $text, array $replyToMessage, $payload): array
    {
        Log::debug('Operator reply:', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'text' => $text,
            'payload' => $payload
        ]);

        // Проверяем, что отвечаем на сообщение бота
        if (isset($replyToMessage['from']['is_bot']) && $replyToMessage['from']['is_bot'] === true) {
            $replyText = $replyToMessage['text'] ?? $replyToMessage['caption'] ?? '';
            $ticketId = $this->findTicketIdInString($replyText);

            if ($ticketId) {
                $ticket = Ticket::query()->find($ticketId);
                $messageText = $text;
                $isSuccess = false;

                try {
                    if (!$ticket) {
                        throw new \Exception('Ticket not found');
                    }

                    $ticketUser = $ticket->user;

                    if (!$ticketUser) {
                        throw new \Exception('Ticket user not found');
                    }

                    $partner = $ticketUser->partner;

                    if (!$partner) {
                        throw new \Exception('Partner not found');
                    }

                    $messageService = app(MessageService::class);

                    // Обычное сообщение
                    if (trim($messageText) !== '') {
                        $messageData = [
                            'message' => $messageText,
                        ];

                        $messageService->sendMessage(
                            $ticket,
                            $messageData,
                            $partner
                        );

                        $isSuccess = true;
                    }

                    // Сообщение файл
                    if (trim($messageText) === '' && isset($payload['message']['document'])) {
                        $caption = $payload['message']['caption'] ?? 'Вложение';
                        $fileId = $payload['message']['document']['file_id'] ?? null;
                        $fileName = $payload['message']['document']['file_name'] ?? null;

                        $messageData = [
                            'message' => $caption,
                            'attachments' => []
                        ];

                        if ($fileId && $fileName) {
                            $uploadedFile = $this->downloadFileFromTelegram($fileId, $fileName);
                            $messageData['attachments'][] = $uploadedFile;

                            $messageService->sendMessage(
                                $ticket,
                                $messageData,
                                $partner
                            );

                            unlink($uploadedFile->getPathname());

                            $isSuccess = true;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('TelegramWebhookService: Operator reply failed: ', [
                        'error' => $e->getMessage(),
                    ]);
                    $isSuccess = false;
                }

                if ($isSuccess) {
                    $this->sendTelegramMessage($chatId, "✅ Ответ отправлен в тикет #{$ticketId}");
                } else {
                    $this->sendTelegramMessage($chatId, "❌ Что-то пошло не так при ответе в тикет #{$ticketId}");
                }
            }

            return [
                'status' => 'processed',
                'chat_id' => $chatId,
                'message' => 'Operator reply successfully'
            ];
        }

        return [
            'status' => 'ignored',
            'message' => 'Operator reply on not bot'
        ];
    }

    protected function findTicketIdInString($text): int
    {
        $pattern = '/#\{(\d+)\}/';

        if (preg_match($pattern, $text, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    protected function downloadFileFromTelegram(string $fileId, string $originalFileName): ?UploadedFile
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            Log::error('TelegramWebhookService: BOT token not configured for file download');
            return null;
        }

        $getFileUrl = "https://api.telegram.org/bot{$botToken}/getFile";
        $response = Http::timeout(30)
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->post($getFileUrl, ['file_id' => $fileId]);

        if (!$response->successful()) {
            Log::error('TelegramWebhookService: Failed to get file info from Telegram', [
                'file_id' => $fileId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        if (!isset($data['ok']) || !$data['ok']) {
            Log::error('TelegramWebhookService: Telegram API error getting file info', [
                'file_id' => $fileId,
                'api_response' => $data
            ]);
            return null;
        }

        $filePath = $data['result']['file_path'] ?? null;
        if (!$filePath) {
            Log::error('TelegramWebhookService: File path not found in Telegram response', ['file_id' => $fileId]);
            return null;
        }

        $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

        $fileContentsResponse = Http::timeout(60)
            ->withOptions(['verify' => config('app.env') !== 'local'])->get($fileUrl);

        if (!$fileContentsResponse->successful()) {
            Log::error('TelegramWebhookService: Failed to download file contents from Telegram', [
                'file_id' => $fileId,
                'file_path' => $filePath,
                'status_code' => $fileContentsResponse->status(),
                'response_body' => $fileContentsResponse->body(),
            ]);
            return null;
        }

        $fileContents = $fileContentsResponse->body();
        $fileNameFromPath = basename($filePath);
        $tempFilePath = storage_path('app/private/' . $fileNameFromPath);

        if ($tempFilePath === false) {
            Log::error('TelegramWebhookService: Could not create temporary file');
            return null;
        }

        $bytesWritten = file_put_contents($tempFilePath, $fileContents);

        if ($bytesWritten === false || $bytesWritten === 0) {
            Log::error('TelegramWebhookService: Could not write file contents to temporary file', ['path' => $tempFilePath]);
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            return null;
        }

        return new UploadedFile(
            $tempFilePath,
            $originalFileName,
            null,
            null,
            false
        );
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
