<?php

namespace App\Modules\Alerts\Services;

use App\Jobs\SendAlertJob;
use App\Models\Alert;
use App\Models\Channel;
use App\Models\User;
use App\Modules\Alerts\Channels\EmailChannel;
use App\Modules\Alerts\Channels\WebPushChannel;
use App\Modules\Alerts\Channels\TelegramChannel;
use App\Models\AlertLog;

class AlertService
{
    /**
     * @param string $type - тип уведомления (ключ шаблона)
     * @param $user - получатель
     * @param array $data - данные для подстановки в шаблон
     * @param string|null $channelName - конкретный канал (если null - все активные)
     * @return void
     */
    public function send(string $type, $user, array $data = [], ?string $channelName = null): void
    {
        if ($channelName) {
            $channel = Channel::where('name', $channelName)
                ->where('enabled', true)
                ->first();

            if ($channel) {
                $this->processAlert($type, $user, $data, $channel);
            }
        } else {
            $channels = Channel::where('enabled', true)->get();

            foreach ($channels as $channel) {
                $this->processAlert($type, $user, $data, $channel);
            }
        }
    }

    /**
     * @param string $type
     * @param int $userId
     * @param int $channelId
     * @param array $data
     * @return bool
     */
    public function isDuplicate(string $type, int $userId, int $channelId, array $data): bool
    {
        $recentAlert = Alert::where('type', $type)
            ->where('user_id', $userId)
            ->where('channel_id', $channelId)
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();

        if (!$recentAlert) {
            return false;
        }

        return json_encode($recentAlert->data) === json_encode($data);
    }

    public function processAlert(string $type, User $user, array $data, Channel $channel): void
    {
        if (!$this->isUserSubscribed($user, $channel)) {
            return;
        }

        if ($this->isDuplicate($type, $user->id, $channel->id, $data)) {
            return;
        }

        $alert = Alert::create([
            'type' => $type,
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'data' => $data,
            'scheduled_at' => now(),
        ]);

        $this->dispatchAlert($alert);
    }

    /**
     * @param User $user
     * @param Channel $channel
     * * @return bool
     */
    public function isUserSubscribed(User $user, Channel $channel): bool
    {
        $subscription = \App\Models\Subscription::where('user_id', $user->id)
            ->where('channel_id', $channel->id)
            ->first();

        return $subscription ? $subscription->subscribed : false;
    }

    /**
     * @param Alert $alert
     * @return void
     */
    public function dispatchAlert(Alert $alert): void
    {
        $channelClass = $this->getChannelClass($alert->channel->name);

        if ($channelClass && class_exists($channelClass)) {
            $channelInstance = new $channelClass();

            try {
                $result = $channelInstance->send($alert);
                $this->logAlert($alert, $result ? 'sent' : 'failed');
                if ($result) {
                    $alert->update(['sent_at' => now()]);
                }
            } catch (\Exception $e) {
                $this->logAlert($alert, 'failed', $e->getMessage());
            }
        } else {
            $this->logAlert($alert, 'failed', 'Channel class not found');
        }
    }

    /**
     * @param string $channelName
     * @return string|null
     */
    public function getChannelClass(string $channelName): ?string
    {
        $channelMap = [
            'email' => EmailChannel::class,
            'webpush' => WebPushChannel::class,
            'telegram' => TelegramChannel::class,
        ];

        return $channelMap[$channelName] ?? null;
    }

    /**
     * @param Alert $alert
     * @param string $status
     * @param string|null $error
     * @return void
     */
    public function logAlert(Alert $alert, string $status, ?string $error = null): void
    {
        AlertLog::create([
            'alert_id' => $alert->id,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /**
     * @param User $user
     * @param string $channelName
     * @return bool
     */
    public function subscribe(User $user, string $channelName): bool
    {
        $channel = Channel::where('name', $channelName)->first();

        if (!$channel) {
            return false;
        }

        \App\Models\Subscription::updateOrCreate(
            ['user_id' => $user->id, 'channel_id' => $channel->id],
            ['subscribed' => true]
        );

        return true;
    }

    /**
     * @param User $user
     * @param string $channelName
     * @return bool
     */
    public function unsubscribe(User $user, string $channelName): bool
    {
        $channel = Channel::where('name', $channelName)->first();

        if (!$channel) {
            return false;
        }

        \App\Models\Subscription::updateOrCreate(
            ['user_id' => $user->id, 'channel_id' => $channel->id],
            ['subscribed' => false]
        );

        return true;
    }

    /**
     * @param string $type
     * @param User $user
     * @param array $data
     * @param string|null $channelName
     * @return void
     */
    public function sendQueued(string $type, User $user, array $data = [], ?string $channelName = null): void
    {
        if ($channelName) {
            $channel = Channel::where('name', $channelName)
                ->where('enabled', true)
                ->first();

            if ($channel) {
                $this->processAlertQueued($type, $user, $data, $channel);
            }
        } else {
            $channels = Channel::where('enabled', true)->get();

            foreach ($channels as $channel) {
                $this->processAlertQueued($type, $user, $data, $channel);
            }
        }
    }

    /**
     * @param string $type
     * @param User $user
     * @param array $data
     * @param Channel $channel
     * @return void
     */
    public function processAlertQueued(string $type, User $user, array $data, Channel $channel): void
    {
        if (!$this->isUserSubscribed($user, $channel)) {
            return;
        }

        if ($this->isDuplicate($type, $user->id, $channel->id, $data)) {
            return;
        }

        $alert = Alert::create([
            'type' => $type,
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'data' => $data,
            'scheduled_at' => now(),
        ]);

        SendAlertJob::dispatch($alert);
    }
}
