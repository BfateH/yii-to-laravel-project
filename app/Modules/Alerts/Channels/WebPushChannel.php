<?php

namespace App\Modules\Alerts\Channels;

use App\Modules\Alerts\Interfaces\ChannelInterface;
use App\Models\Alert;
use App\Models\NotificationTemplate;
use App\Models\UserWebPushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushChannel implements ChannelInterface
{
    protected $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ];

        $clientOptions = [
            'timeout' => 30,
            'verify' => env('APP_ENV') !== 'local',
        ];

        $this->webPush = new WebPush($auth, [], null, $clientOptions);
        $this->webPush->setReuseVAPIDHeaders(true);
    }

    /**
     * @param Alert $alert
     * @return bool
     */
    public function send(Alert $alert): bool
    {
        try {
            if (!$this->isUserSubscribedToWebPush($alert->user_id)) {
                Log::info('WebPush: User is not subscribed to web push notifications', [
                    'user_id' => $alert->user_id
                ]);
                return false;
            }

            $subscriptions = UserWebPushSubscription::where('user_id', $alert->user_id)->get();

            if ($subscriptions->isEmpty()) {
                Log::warning('WebPush: User has no web push subscriptions', [
                    'user_id' => $alert->user_id
                ]);
                return false;
            }

            $template = NotificationTemplate::where('key', $alert->type)
                ->where('channel_id', $alert->channel_id)
                ->first();

            if (!$template) {
                Log::warning('WebPush: Template not found', [
                    'type' => $alert->type,
                    'channel_id' => $alert->channel_id
                ]);
                return false;
            }

            $payload = $this->preparePayload($alert, $template);

            $successCount = 0;
            $totalCount = $subscriptions->count();

            foreach ($subscriptions as $userSubscription) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $userSubscription->endpoint,
                        'keys' => [
                            'p256dh' => $userSubscription->p256dh,
                            'auth' => $userSubscription->auth
                        ]
                    ]);

                    $report = $this->webPush->sendOneNotification($subscription, json_encode($payload));

                    if ($report->isSuccess()) {
                        $successCount++;
                    } else {
                        Log::error('WebPush notification failed for one subscription', [
                            'alert_id' => $alert->id,
                            'user_id' => $alert->user_id,
                            'endpoint' => $userSubscription->endpoint,
                            'reason' => $report->getReason()
                        ]);

                        if ($this->isSubscriptionExpired($report->getReason())) {
                            $userSubscription->delete();
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('WebPush send error for subscription', [
                        'alert_id' => $alert->id,
                        'user_id' => $alert->user_id,
                        'endpoint' => $userSubscription->endpoint,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('WebPush notifications sent', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'success' => $successCount,
                'total' => $totalCount
            ]);

            return $successCount > 0;

        } catch (\Exception $e) {
            Log::error('WebPush channel error', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function isUserSubscribedToWebPush(int $userId): bool
    {
        $channel = \App\Models\Channel::where('name', 'webpush')->first();

        if (!$channel) {
            return false;
        }

        $subscription = \App\Models\Subscription::where('user_id', $userId)
            ->where('channel_id', $channel->id)
            ->first();

        return $subscription && $subscription->subscribed;
    }

    protected function preparePayload(Alert $alert, NotificationTemplate $template): array
    {
        $data = $alert->data ?? [];

        $title = $this->parseTemplate($template->subject ?? 'Уведомление', $data);
        $body = $this->parseTemplate($template->body, $data);

        return [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/notification-icon.png',
            'badge' => '/images/notification-badge.png',
            'data' => [
                'alert_id' => $alert->id,
                'type' => $alert->type,
                'timestamp' => now()->toISOString()
            ]
        ];
    }

    protected function parseTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    protected function isSubscriptionExpired(string $reason): bool
    {
        $expiredReasons = [
            'Expired subscription',
            'Subscription has expired',
            '410',
            '404',
        ];

        foreach ($expiredReasons as $expiredReason) {
            if (stripos($reason, $expiredReason) !== false) {
                return true;
            }
        }

        return false;
    }
}
