<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Modules\Alerts\Services\AlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [1, 5, 10];

    protected $alert;

    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }

    public function handle(AlertService $alertService)
    {
        try {
            $channelClass = $alertService->getChannelClass($this->alert->channel->name);

            if ($channelClass && class_exists($channelClass)) {
                $channelInstance = new $channelClass();
                $result = $channelInstance->send($this->alert);

                if (!$result) {
                    throw new \Exception("Failed to send alert through {$this->alert->channel->name}");
                }

                $this->alert->update(['sent_at' => now()]);
                $alertService->logAlert($this->alert, 'sent');
            }
        } catch (\Exception $e) {
            app(AlertService::class)->logAlert($this->alert, 'failed', $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                Log::error("Alert failed after {$this->tries} attempts", [
                    'alert_id' => $this->alert->id,
                    'error' => $e->getMessage()
                ]);
            }

            throw $e;
        }
    }
}
