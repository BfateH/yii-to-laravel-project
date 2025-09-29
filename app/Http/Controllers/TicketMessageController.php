<?php
// app/Http/Controllers/TicketMessageApiController.php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\SupportChat\Enums\TicketStatus;
use App\Modules\SupportChat\Resources\MessageResource;
use App\Modules\SupportChat\Services\MessageService;
use App\Services\TelegramApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TicketMessageController extends Controller
{
    protected MessageService $messageService;
    protected TelegramApiService $telegramApiService;

    public function __construct(MessageService $messageService, TelegramApiService $telegramApiService)
    {
        $this->messageService = $messageService;
        $this->telegramApiService = $telegramApiService;
    }

    /**
     *
     * @param Ticket $ticket
     * @return AnonymousResourceCollection|JsonResponse
     */
    public function index(Ticket $ticket): JsonResponse|AnonymousResourceCollection
    {
        $user = User::query()->findOrFail(Auth::id());
        $canAccess = $this->canAccessTicket($ticket, $user);

        if (!$canAccess) {
            return response()->json(['error' => 'Доступ запрещен.'], 403);
        }

        try {
            $messages = $ticket->messages()->with(['user', 'attachments', 'ticket'])->orderBy('created_at')->get();
            $lastMessage = $messages->last();

            if ($lastMessage) {
                if ($user->isAdminRole() || $user->isPartnerRole()) {
                    $ticket->update(['last_admin_message_read' => $lastMessage->id]);
                } else {
                    $ticket->update(['last_user_message_read' => $lastMessage->id]);
                }
            }

            return MessageResource::collection($messages);
        } catch (\Exception $e) {
            Log::error('Ошибка при загрузке сообщений тикета: ' . $e->getMessage(), ['ticket_id' => $ticket->id]);
            return response()->json(['error' => 'Ошибка сервера при загрузке сообщений.'], 500);
        }
    }

    public function store(Request $request, Ticket $ticket): MessageResource|JsonResponse
    {
        $user = User::query()->findOrFail(Auth::id());
        $canAccess = $this->canAccessTicket($ticket, $user);

        if (!$canAccess) {
            return response()->json(['error' => 'Доступ запрещен.'], 403);
        }

        $isOpen = in_array($ticket->status, [
            TicketStatus::OPEN->value ?? 'open',
            TicketStatus::IN_PROGRESS->value ?? 'in_progress'
        ]);

        if (!$isOpen) {
            return response()->json(['error' => 'Невозможно отправить сообщение в закрытый тикет.'], 400);
        }

        try {
            $validatedData = $request->validate([
                'message' => 'required|string|max:5000',
                'attachments' => 'nullable|array|max:5',
                'attachments.*' => 'nullable|file|max:10240'
            ], [
                'message.required' => 'Сообщение обязательно для заполнения.',
                'message.max' => 'Сообщение не должно превышать 5000 символов.',
                'attachments.max' => 'Можно загрузить максимум 5 файлов.',
                'attachments.*.file' => 'Каждый файл должен быть действительным файлом.',
                'attachments.*.max' => 'Каждый файл не должен превышать 10MB.'
            ]);

            $message = $this->messageService->sendMessage(
                $ticket,
                $validatedData,
                $user
            );

            $message->load(['user', 'attachments', 'ticket']);
            // отправка в группы - отедельная лоигка от основных уведомлений
            $this->sendTelegramNotification($ticket, $message, $user);

            return MessageResource::make($message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in ticket message store: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'errors' => $e->errors()
            ]);
            return response()->json(['error' => 'Ошибка валидации: ' . implode(', ', array_flatten($e->errors()))], 422);

        } catch (\Exception $e) {
            Log::error('Ошибка при создании сообщения в тикете: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message_data' => $request->only(['message']),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Ошибка сервера при отправке сообщения: ' . $e->getMessage()], 500);
        }
    }

    protected function canAccessTicket(Ticket $ticket, User $user): bool
    {
        $canAccess = false;

        if ($user && ($user->isAdminRole() || ($ticket->user_id === $user->id))) {
            $canAccess = true;
        }

        if ($user && $user->isPartnerRole()) {
            $partner = $ticket->user->partner;
            if ($partner && $partner->id === $user->id) {
                $canAccess = true;
            }
        }

        return $canAccess;
    }

    private function sendTelegramNotification(Ticket $ticket, $message, User $user): void
    {
        $attachments = $message->attachments->toArray();

        if ($ticket->message_thread_id && $user->isPartnerRole() && $user->telegram_support_chat_id) {
            $text = $this->formatTelegramNotificationText($ticket, $message, $user);

            $result = $this->telegramApiService->sendTextAndAttachmentsMessage(
                $user->telegram_support_chat_id,
                $text,
                ['message_thread_id' => $ticket->message_thread_id],
                $attachments
            );

            if (!$result['success']) {
                Log::warning('Failed to send Telegram notification for ticket message', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                    'result' => $result,
                ]);
            }
        }

        if ($ticket->message_thread_id && $user->isDefaultUserRole()) {
            $partner = $user->partner;

            if ($partner && $partner->telegram_support_chat_id) {
                $text = $this->formatTelegramNotificationText($ticket, $message, $user);

                $result = $this->telegramApiService->sendTextAndAttachmentsMessage(
                    $partner->telegram_support_chat_id,
                    $text,
                    ['message_thread_id' => $ticket->message_thread_id],
                    $attachments
                );

                if (!$result['success']) {
                    Log::warning('Failed to send Telegram notification for ticket message', [
                        'ticket_id' => $ticket->id,
                        'message_id' => $message->id,
                        'result' => $result,
                    ]);
                }
            }
        }
    }

    private function formatTelegramNotificationText(Ticket $ticket, $message, User $user): string
    {
        $text = "<b>🔔 Новое сообщение в тикете #{{$ticket->id}}</b>\n";

        $messageText = trim($message->message);
        $text .= "<b>Сообщение:</b> " . e($messageText) . "\n";

        return $text;
    }

}
