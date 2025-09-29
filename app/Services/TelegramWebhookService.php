<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Ticket;
use App\Modules\SupportChat\Enums\TicketStatus;
use App\Modules\SupportChat\Services\MessageService;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramWebhookService
{
    private TelegramApiService $telegramApiService;
    private MessageService $messageService;

    public function __construct(TelegramApiService $telegramApiService, MessageService $messageService)
    {
        $this->telegramApiService = $telegramApiService;
        $this->messageService = $messageService;
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
        if ($existingUser = User::query()->where('telegram_id', $chatId)->first()) {
            $this->telegramApiService->sendMessage($chatId, "✅ Ваш Telegram уже привязан к аккаунту {$existingUser->email}");

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

        if (!Str::startsWith($text, 'secret_token_')) {
            $this->telegramApiService->sendMessage($chatId, "Привет! Для подключения уведомлений отправьте ваш секретный код для привязки телеграм бота.");
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

        if (isset($replyToMessage['from']['is_bot']) && $replyToMessage['from']['is_bot'] === true) {
            $replyText = $replyToMessage['text'] ?? $replyToMessage['caption'] ?? '';
            $ticketId = $this->findTicketIdInString($replyText);

            Log::debug('OPERATOR REPLY TICKET ID: ' . $ticketId);

            if ($ticketId) {
                $ticket = Ticket::query()->find($ticketId);

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

                    if($ticket->status === TicketStatus::CLOSED->value) {
                        $this->telegramApiService->sendMessage($chatId, "❌ Тикет #{{$ticketId}} закрыт.");
                        return [
                            'status' => 'processed',
                            'chat_id' => $chatId,
                            'message' => 'Operator reply successfully'
                        ];
                    }

                    $isSuccess = false;

                    // Обычное текстовое сообщение
                    if (trim($text) !== '') {
                        $messageData = ['message' => $text];
                        $this->messageService->sendMessage($ticket, $messageData, $partner);
                        $isSuccess = true;
                        $this->telegramApiService->sendMessage($chatId, "✅ Ответ отправлен в тикет #{$ticketId}");
                    }
                    // Файл типа document
                    elseif (isset($payload['message']['document'])) {
                        $isSuccess = $this->processDocumentAttachment($payload['message']['document'], $chatId, $ticketId, $ticket, $partner, $payload['message']['caption'] ?? '');
                    }
                    // Файл типа photo
                    elseif (isset($payload['message']['photo'])) {
                        $isSuccess = $this->processPhotoAttachment($payload['message']['photo'], $chatId, $ticketId, $ticket, $partner, $payload['message']['caption'] ?? '');
                    }

                    if (!$isSuccess) {
                        $this->telegramApiService->sendMessage($chatId, "❌ Что-то пошло не так при ответе в тикет #{$ticketId}");
                    }

                    return [
                        'status' => 'processed',
                        'chat_id' => $chatId,
                        'message' => 'Operator reply successfully'
                    ];

                } catch (\Exception $e) {
                    Log::error('TelegramWebhookService: Operator reply failed: ', [
                        'error' => $e->getMessage(),
                    ]);
                    $this->telegramApiService->sendMessage($chatId, "❌ Что-то пошло не так при ответе в тикет #{$ticketId}");
                }
            }
        }

        return [
            'status' => 'ignored',
            'message' => 'Operator reply on not bot'
        ];
    }

    private function processDocumentAttachment(array $documentData, int $chatId, int $ticketId, Ticket $ticket, User $partner, string $caption): bool
    {
        $fileId = $documentData['file_id'] ?? null;
        $fileName = $documentData['file_name'] ?? null;

        if ($fileId && $fileName) {
            $uploadedFile = $this->telegramApiService->downloadFileAsUploadedFile($fileId, $fileName);
            if ($uploadedFile) {
                $messageData = [
                    'message' => $caption,
                    'attachments' => [$uploadedFile]
                ];

                $this->messageService->sendMessage($ticket, $messageData, $partner);
                unlink($uploadedFile->getPathname());
                $this->telegramApiService->sendMessage($chatId, "✅ Файл $fileName отправлен в тикет #{$ticketId}");
                return true;
            } else {
                $this->telegramApiService->sendMessage($chatId, "❌ Файл $fileName не отправлен в тикет #{$ticketId} (ошибка загрузки).");
            }
        } else {
            $this->telegramApiService->sendMessage($chatId, "❌ Файл не отправлен в тикет #{$ticketId} (данные отсутствуют).");
        }
        return false;
    }

    private function processPhotoAttachment(array $photoData, int $chatId, int $ticketId, Ticket $ticket, User $partner, string $caption): bool
    {
        if (!empty($photoData)) {
            // Берём фото самого высокого разрешения (последний элемент в массиве)
            $largestPhoto = end($photoData);
            $fileId = $largestPhoto['file_id'] ?? null;

            if ($fileId) {
                $uploadedFile = $this->telegramApiService->downloadFileAsUploadedFile($fileId, 'photo.jpg');
                if ($uploadedFile) {
                    $messageData = [
                        'message' => $caption,
                        'attachments' => [$uploadedFile]
                    ];

                    $this->messageService->sendMessage($ticket, $messageData, $partner);
                    unlink($uploadedFile->getPathname());
                    $this->telegramApiService->sendMessage($chatId, "✅ Файл {$uploadedFile->getClientOriginalName()} отправлен в тикет #{$ticketId}");
                    return true;
                } else {
                    $this->telegramApiService->sendMessage($chatId, "❌ Фото не отправлено в тикет #{$ticketId} (ошибка загрузки).");
                }
            } else {
                $this->telegramApiService->sendMessage($chatId, "❌ Фото не отправлено в тикет #{$ticketId} (file_id отсутствует).");
            }
        } else {
            $this->telegramApiService->sendMessage($chatId, "❌ Фото не отправлено в тикет #{$ticketId} (данные отсутствуют).");
        }
        return false;
    }

    protected function findTicketIdInString($text): int
    {
        $pattern = '/#\{(\d+)}/';
        if (preg_match($pattern, $text, $matches)) {
            return (int)$matches[1];
        }
        return 0;
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
            $this->telegramApiService->sendMessage($chatId, "❌ Для привязки группы в неё должен написать партнёр.");
            return [
                'status' => 'ignored',
                'message' => 'Sender is not a partner'
            ];
        }

        $user->telegram_support_chat_id = $chatId;
        $user->save();

        $this->telegramApiService->sendMessage($chatId, "✅ Группа успешно привязана к партнёру {$user->email}");

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

    private function linkTelegramByEmailAddress(string $chatId, string $secretToken): array
    {
        $user = User::query()->where('secret_code_telegram', $secretToken)->first();

        if (!$user) {
            Log::warning('TelegramWebhookService: User not found', [
                'chat_id' => $chatId,
            ]);

            $this->telegramApiService->sendMessage($chatId, "❌ Пользователь с таким секертным кодом не найден в нашей системе.");

            return [
                'status' => 'error',
                'chat_id' => $chatId,
                'message' => 'User not found'
            ];
        }

        if (!$user->telegram_id) {
            $user->update(['telegram_id' => $chatId]);

            Log::info('TelegramWebhookService: Linked by secret token', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
            ]);

            $this->telegramApiService->sendMessage($chatId, "✅ Ваш Telegram успешно привязан к аккаунту {$user->email}!");
        } else {
            if ($user->telegram_id === $chatId) {
                $this->telegramApiService->sendMessage($chatId, "✅ Ваш Telegram уже привязан к аккаунту {$user->email}!");
            } else {
                $this->telegramApiService->sendMessage($chatId, "✅ Ваш Telegram уже был привязан к аккаунту {$user->email}. Уведомления приходят на привязанный аккаунт.");
            }
        }

        return [
            'status' => 'processed',
            'chat_id' => $chatId,
            'system_user_id' => $user->id,
            'action' => 'linked_by_secret_token'
        ];
    }
}
