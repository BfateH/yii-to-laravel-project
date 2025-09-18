<?php

namespace App\Modules\Tracking\Providers\RussianPost;

use App\Modules\OrderManagement\Models\Package;
use App\Modules\Tracking\Contracts\TrackingProviderInterface;
use App\Modules\Tracking\Providers\RussianPost\DTOs\OperationHistoryRequestDTO;
use Illuminate\Support\Facades\Log;

class RussianPostProvider implements TrackingProviderInterface
{
    private RussianPostClient $client;
    public function __construct(RussianPostClient $client)
    {
        $this->client = $client;
    }

    public function getTrackingHistory(Package $package): array
    {
        $trackingNumber = $package->tracking_number ?? null;

        if (empty($trackingNumber)) {
            Log::channel('tracking')->warning('RussianPostProvider: Tracking number is empty for package ID ' . $package->id);
            return [];
        }

        Log::channel('tracking')->info('RussianPostProvider: Fetching tracking history for package ID ' . $package->id . ' with barcode ' . $trackingNumber);

        try {
            $requestDto = new OperationHistoryRequestDTO(
                barcode: $trackingNumber,
                messageType: 0,
                language: 'RUS'
            );

            $historyEvents = $this->client->getOperationHistory($requestDto);
            Log::channel('tracking')->info('RussianPostProvider: Successfully fetched ' . count($historyEvents) . ' tracking events for package ID ' . $package->id);
            return $historyEvents;

        } catch (\Exception $e) {
            Log::channel('tracking')->error('RussianPostProvider: Error fetching tracking history for package ID ' . $package->id, [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getPostalOrderEvents(Package $package): array
    {
        $trackingNumber = $package->tracking_number ?? null;

        if (empty($trackingNumber)) {
            Log::channel('tracking')->warning('RussianPostProvider: Tracking number is empty for package ID ' . $package->id . ' (PostalOrderEvents)');
            return [];
        }

        Log::channel('tracking')->info('RussianPostProvider: Fetching postal order events for package ID ' . $package->id . ' with barcode ' . $trackingNumber);

        try {
            $postalOrderEvents = $this->client->getPostalOrderEvents($trackingNumber, 'RUS');
            Log::channel('tracking')->info('RussianPostProvider: Successfully fetched ' . count($postalOrderEvents) . ' postal order events for package ID ' . $package->id);
            return $postalOrderEvents;

        } catch (\Exception $e) {
            Log::channel('tracking')->error('RussianPostProvider: Error fetching postal order events for package ID ' . $package->id, [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getIdentifier(): string
    {
        return 'RUSSIAN_POST';
    }
}
