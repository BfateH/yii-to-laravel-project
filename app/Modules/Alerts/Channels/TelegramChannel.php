<?php

namespace App\Modules\Alerts\Channels;

use App\Models\Alert;
use App\Modules\Alerts\Interfaces\ChannelInterface;
use App\Services\TelegramApiService; // Убедитесь, что путь правильный
use Illuminate\Support\Facades\Log;

class TelegramChannel implements ChannelInterface
{
    public function __construct(
        protected TelegramApiService $telegramApiService
    ) {
    }

    public function send(Alert $alert): bool
    {
        try {
            $telegramId = $this->getUserTelegramId($alert->user_id);

            if (!$telegramId) {
                Log::warning('Alert TelegramChannel: User has no Telegram ID', [
                    'user_id' => $alert->user_id
                ]);
                return false;
            }

            $message = $this->formatMessage($alert);
            $attachments = $alert->data['attachments'] ?? [];

            Log::info('Telegram message to be sent', [
                'alert_id' => $alert->id,
                'chat_id' => $telegramId,
                'message' => $message,
                'has_attachments' => !empty($attachments),
            ]);

            $result = $this->telegramApiService->sendTextAndAttachmentsMessage(
                $telegramId,
                $message,
                ['parse_mode' => 'HTML'],
                $attachments
            );

            return $result['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('Alert TelegramChannel: Error', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    protected function getUserTelegramId(int $userId): ?string
    {
        $user = \App\Models\User::find($userId);
        return $user?->telegram_id;
    }

    protected function formatMessage(Alert $alert): string
    {
        $template = \App\Models\NotificationTemplate::where('key', $alert->type)
            ->where('channel_id', $alert->channel_id)
            ->first();

        if ($template) {
            $body = $template->body;
            $data = $alert->data ?? [];

            foreach ($data as $key => $value) {
                if ($key === 'attachments') continue;
                $body = str_replace('{' . $key . '}', $value, $body);
            }

            return $body;
        }

        $data = $alert->data ?? [];
        $message = "🔔 Уведомление: " . $alert->type . "\n\n";
        foreach ($data as $key => $value) {
            if ($key === 'attachments') continue;
            $message .= ucfirst($key) . ": " . $value . "\n";
        }

        return $message;
    }
}
