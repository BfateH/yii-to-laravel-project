<?php

namespace App\Jobs;

use App\Modules\OrderManagement\Models\Package;
use App\Modules\Tracking\Exceptions\TrackingException;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePackageStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public bool $deleteWhenMissingModels = true;

    protected Package $package;
    protected bool $forceRefresh;
    public function __construct(Package $package, bool $forceRefresh = false)
    {
        $this->package = $package;
        $this->forceRefresh = $forceRefresh;
    }

    public function handle(TrackingService $trackingService): void
    {
        try {
            Log::channel('tracking')->info("UpdatePackageStatusJob: Starting update for package ID {$this->package->id}", [
                'tracking_number' => $this->package->tracking_number,
                'force_refresh' => $this->forceRefresh
            ]);

            $result = $trackingService->trackPackage($this->package, $this->forceRefresh);

            Log::channel('tracking')->info("UpdatePackageStatusJob: Successfully finished update for package ID {$this->package->id}", [
                'events_count' => count($result['history']),
                'postal_order_events_count' => count($result['postal_order_events']),
                'from_cache' => $result['from_cache']
            ]);

        } catch (TrackingException $e) {
            Log::channel('tracking')->error("UpdatePackageStatusJob: TrackingException for package ID {$this->package->id}", [
                'tracking_number' => $this->package->tracking_number,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            Log::channel('tracking')->error("UpdatePackageStatusJob: Unexpected error for package ID {$this->package->id}", [
                'tracking_number' => $this->package->tracking_number,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}

?>
