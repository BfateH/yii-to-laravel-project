<?php

namespace App\Modules\Alerts\Channels;

use App\Models\Alert;
use App\Modules\Alerts\Interfaces\ChannelInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipStream\ZipStream;

class TelegramChannel implements ChannelInterface
{
    protected string $botToken;
    protected string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->apiUrl = 'https://api.telegram.org/bot' . $this->botToken;
    }

    public function send(Alert $alert, $toCurrentUser = false): bool
    {
        try {
            $telegramId = $this->getUserTelegramId($alert->user_id);
            $telegramSupportChatId = $this->getTelegramSupportChatId($alert->user_id);

            if (!$telegramId && !$telegramSupportChatId) {
                Log::warning('Alert TelegramChannel: User has no Telegram ID and Telegram Chat ID', [
                    'user_id' => $alert->user_id
                ]);
                return false;
            }

            $chatId = $toCurrentUser ? $telegramId : $telegramSupportChatId;
            $message = $this->formatMessage($alert);
            $attachments = $alert->data['attachments'] ?? [];

            Log::info('Telegram message to be sent', [
                'alert_id' => $alert->id,
                'chat_id' => $chatId ?? $telegramId,
                'message' => $message,
                'has_attachments' => !empty($attachments),
            ]);

            if (empty($attachments)) {
                return $this->sendTextMessage($chatId ?? $telegramId, $message);
            }

            $zipPath = $this->createAttachmentsArchive($attachments);
            if (!$zipPath || !file_exists($zipPath)) {
                Log::error('Failed to create ZIP archive for attachments', [
                    'alert_id' => $alert->id,
                ]);
                return $this->sendTextMessage($chatId ?? $telegramId, $message);
            }

            $result = $this->sendDocumentMessage($chatId ?? $telegramId, $zipPath, $message);
            unlink($zipPath);
            return $result;
        } catch (\Exception $e) {
            Log::error('Alert TelegramChannel: Error', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    protected function sendTextMessage(string $chatId, string $text): bool
    {
        $response = Http::timeout(30)
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

        return $this->handleResponse($response, 'text');
    }

    protected function sendDocumentMessage(string $chatId, string $zipPath, string $caption): bool
    {
        $zipFileName = 'Вложения.zip';

        $response = Http::timeout(30)
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->attach('document', file_get_contents($zipPath), $zipFileName)
            ->post("{$this->apiUrl}/sendDocument", [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);

        return $this->handleResponse($response, 'document');
    }

    protected function createAttachmentsArchive(array $attachments): ?string
    {
        $fileName = 'attachments_' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.zip';
        $absolutePath = storage_path('app/private/' . $fileName);

        $outputStream = fopen($absolutePath, 'wb');
        if (!$outputStream) {
            Log::error('Cannot create ZIP file in private storage', ['path' => $absolutePath]);
            return null;
        }

        $zip = new \ZipStream\ZipStream(outputStream: $outputStream);

        foreach ($attachments as $attachment) {
            $relativeFilePath = $attachment['file_path'] ?? null;
            if (empty($relativeFilePath)) continue;

            $originalName = $attachment['original_name'] ?? basename($relativeFilePath);
            $fullFilePath = storage_path('app/public/' . ltrim($relativeFilePath, '/'));

            if (!is_file($fullFilePath)) {
                Log::warning('Attachment file not found', ['path' => $fullFilePath]);
                continue;
            }

            $content = file_get_contents($fullFilePath);
            if ($content !== false) {
                $zip->addFile($originalName, $content);
            }
        }

        $zip->finish();
        fclose($outputStream);

        return file_exists($absolutePath) ? $absolutePath : null;
    }

    protected function handleResponse($response, string $type): bool
    {
        if ($response->successful()) {
            $responseData = $response->json();
            $messageId = $responseData['result']['message_id'] ?? null;

            Log::info("Alert TelegramChannel: {$type} message sent", [
                'message_id' => $messageId,
                'type' => $type,
            ]);

            return true;
        }

        Log::error("Alert TelegramChannel: Failed to send {$type} message", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    protected function getUserTelegramId(int $userId): ?string
    {
        $user = \App\Models\User::find($userId);
        return $user?->telegram_id;
    }

    protected function getTelegramSupportChatId(int $userId): ?string
    {
        $user = \App\Models\User::find($userId);
        return $user?->telegram_support_chat_id;
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
