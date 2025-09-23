<?php

namespace App\Modules\Alerts\Channels;

use App\Modules\Alerts\Interfaces\ChannelInterface;
use App\Models\Alert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel implements ChannelInterface
{
    protected $botToken;
    protected $apiUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->apiUrl = 'https://api.telegram.org/bot' . $this->botToken;
    }

    public function send(Alert $alert): bool
    {
        try {
            $telegramId = $this->getUserTelegramId($alert->user_id);

            if (!$telegramId) {
                Log::warning('Alert TelegramChannel: User has no Telegram ID', ['user_id' => $alert->user_id]);
                return false;
            }

            $message = $this->formatMessage($alert);

            Log::info('Telegram message to be sent', [
                'alert_id' => $alert->id,
                'chat_id' => $telegramId,
                'message' => $message,
                'parse_mode' => 'HTML'
            ]);

            $response = Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($this->apiUrl . '/sendMessage', [
                    'chat_id' => $telegramId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['result']['message_id'])) {
                    Log::info('Alert TelegramChannel: Message sent', [
                        'alert_id' => $alert->id,
                        'message_id' => $responseData['result']['message_id'],
                        'chat_id' => $telegramId
                    ]);
                }

                return true;
            } else {
                Log::error('Alert TelegramChannel: Telegram API error', [
                    'alert_id' => $alert->id,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Alert TelegramChannel: Error', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function getUserTelegramId(int $userId): ?string
    {
        $user = \App\Models\User::find($userId);
        return $user && !empty($user->telegram_id) ? $user->telegram_id : null;
    }

    protected function formatMessage(Alert $alert): string
    {
        $template = \App\Models\NotificationTemplate::where('key', $alert->type)
            ->where('channel_id', $alert->channel_id)
            ->first();

        if ($template) {
            $data = $alert->data ?? [];
            $body = $template->body;

            foreach ($data as $key => $value) {
                $body = str_replace('{' . $key . '}', $value, $body);
            }

            return $body;
        }

        $data = $alert->data ?? [];
        $message = "🔔 Уведомление: " . $alert->type . "\n\n";

        foreach ($data as $key => $value) {
            $message .= ucfirst($key) . ": " . $value . "\n";
        }

        return $message;
    }
}
