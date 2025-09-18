<?php

namespace App\Modules\Tracking\Services;

use App\Models\TrackingEvent;
use App\Modules\OrderManagement\Enums\PackageStatus;
use App\Modules\OrderManagement\Models\Package;
use App\Modules\Tracking\Contracts\TrackingProviderInterface;
use App\Modules\Tracking\Enums\ErrorTypeEnum;
use App\Modules\Tracking\Exceptions\TrackingException;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\OperationHistoryResponseDTO;
use App\Modules\Tracking\Providers\RussianPost\DTOs\Responses\PostalOrderEventResponseDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    private TrackingProviderInterface $provider;
    private TrackingRateLimitService $rateLimitService;
    private TrackingCacheService $cacheService;

    private int $maxRetries = 3;
    private int $baseDelayMs = 1000;
    private int $antiFloodMinutes = 5;

    public function __construct(
        TrackingProviderInterface $provider,
        TrackingRateLimitService  $rateLimitService,
        TrackingCacheService      $cacheService
    )
    {
        $this->provider = $provider;
        $this->rateLimitService = $rateLimitService;
        $this->cacheService = $cacheService;
    }

    public function trackPackage(Package $package, bool $forceRefresh = false): array
    {
        $providerId = $this->provider->getIdentifier();
        Log::channel('tracking')->info("TrackingService: Starting tracking for package ID {$package->id} using provider {$providerId}" . ($forceRefresh ? ' (forced refresh)' : ''));

        if (!$forceRefresh && !$this->canUpdate($package)) {
            Log::channel('tracking')->info("TrackingService: Skipping update for package ID {$package->id} due to anti-flood rule.");
            $cachedHistory = $this->cacheService->getTrackingHistory($package);
            $cachedPostalOrder = $this->cacheService->getPostalOrderEvents($package);
            return [
                'history' => $cachedHistory ?? [],
                'postal_order_events' => $cachedPostalOrder ?? [],
                'from_cache' => $cachedHistory !== null && $cachedPostalOrder !== null
            ];
        }

        if ($this->rateLimitService->isLimitExceeded($providerId)) {
            Log::channel('tracking')->warning("TrackingService: Rate limit exceeded for provider {$providerId} for package ID {$package->id}");
            throw new TrackingException("Rate limit exceeded for provider {$providerId}", 429);
        }

        $result = [
            'history' => [],
            'postal_order_events' => [],
            'from_cache' => false
        ];

        if (!$forceRefresh) {
            $cachedHistory = $this->cacheService->getTrackingHistory($package);
            $cachedPostalOrder = $this->cacheService->getPostalOrderEvents($package);

            if ($cachedHistory !== null && $cachedPostalOrder !== null) {
                $result['history'] = $cachedHistory;
                $result['postal_order_events'] = $cachedPostalOrder;
                $result['from_cache'] = true;
                Log::channel('tracking')->info("TrackingService: Data for package ID {$package->id} retrieved from cache");
                return $result;
            }
        }

        $historyEvents = [];
        $postalOrderEvents = [];

        try {
            $historyEvents = $this->retryOnFailure(fn() => $this->provider->getTrackingHistory($package));
            $postalOrderEvents = $this->retryOnFailure(fn() => $this->provider->getPostalOrderEvents($package));

            $this->saveTrackingEvents($package, $historyEvents);
            $this->savePostalOrderEvents($package, $postalOrderEvents);
            $this->updatePackageStatus($package, $historyEvents);

            $this->cacheService->putTrackingHistory($package, $historyEvents);
            $this->cacheService->putPostalOrderEvents($package, $postalOrderEvents);

            $this->updateLastTrackingAt($package); // Для анти-флуда
            $this->updateLastTrackingTimestamp($package); // Для планирования опроса

            $result['history'] = $historyEvents;
            $result['postal_order_events'] = $postalOrderEvents;

            Log::channel('tracking')->info("TrackingService: Successfully tracked package ID {$package->id}. Fetched " . count($historyEvents) . " history events and " . count($postalOrderEvents) . " postal order events.");

        } catch (\Exception $e) {
            Log::channel('tracking')->error("TrackingService: Failed to track package ID {$package->id} after retries", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorType = $this->classifyError($e);
            $this->saveTrackingError($package, $e, $errorType);

            throw new TrackingException("Failed to track package ID {$package->id}: " . $e->getMessage(), $e->getCode(), $e);
        }

        return $result;
    }

    public function trackPackages(array $packages, bool $forceRefresh = false): array
    {
        $results = [];
        foreach ($packages as $package) {
            try {
                $results[$package->id] = $this->trackPackage($package, $forceRefresh);
            } catch (\Exception $e) {
                Log::channel('tracking')->error("TrackingService: Error tracking package ID {$package->id} in batch", [
                    'exception' => $e->getMessage()
                ]);
                $results[$package->id] = ['error' => $e->getMessage(), 'code' => $e->getCode()];
            }
        }
        return $results;
    }

    private function retryOnFailure(callable $callback)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                $shouldRetry = $this->shouldRetry($e);

                if ($attempt > $this->maxRetries || !$shouldRetry) {
                    Log::channel('tracking')->warning("TrackingService: Retry limit reached or retry not allowed for exception: " . $e->getMessage());
                    break;
                }

                $delayMs = $this->baseDelayMs * (2 ** ($attempt - 1));
                $jitterMs = rand(0, (int)($delayMs * 0.1));
                $totalDelayMs = $delayMs + $jitterMs;

                Log::channel('tracking')->warning("TrackingService: Retrying after error (attempt {$attempt}/{$this->maxRetries}). Delaying for {$totalDelayMs}ms. Error: " . $e->getMessage());
                usleep($totalDelayMs * 1000);
            }
        }

        throw $lastException;
    }

    private function shouldRetry(\Exception $e): bool
    {
        $code = $e->getCode();
        return in_array($code, [0, 500, 502, 503, 504, 429], true);
    }

    private function classifyError(\Exception $e): ErrorTypeEnum
    {
        $code = $e->getCode();
        $message = strtolower($e->getMessage());

        if (in_array($code, [400], true) || str_contains($message, 'invalid track number') || str_contains($message, 'operationhistoryfault')) {
            return ErrorTypeEnum::CLIENT;
        } elseif ($code === 404) {
            return ErrorTypeEnum::CLIENT;
        } elseif (in_array($code, [500, 502, 503, 504], true)) {
            return ErrorTypeEnum::SERVER;
        } elseif ($code === 429) {
            return ErrorTypeEnum::RATE_LIMIT;
        } elseif ($code === 0 || str_contains($message, 'curl') || str_contains($message, 'soap') || str_contains($message, 'connection') || str_contains($message, 'timeout')) {
            return ErrorTypeEnum::NETWORK;
        } else {
            return ErrorTypeEnum::UNKNOWN;
        }
    }

    private function saveTrackingEvents(Package $package, array $historyEvents): void
    {
        Log::channel('tracking')->debug("TrackingService: Saving " . count($historyEvents) . " tracking events for package ID {$package->id}");

        foreach ($historyEvents as $eventDto) {
            if ($eventDto instanceof OperationHistoryResponseDTO) {
                $exists = $package->trackingEvents()->where('operation_date', $eventDto->operationDate)
                    ->where('operation_type_id', $eventDto->operationTypeId)
                    ->exists();

                if (!$exists) {
                    try {
                        $trackingEvent = new TrackingEvent($eventDto->toTrackingEventAttributes());
                        $trackingEvent->package_id = $package->id;
                        $trackingEvent->save();
                        Log::channel('tracking')->debug("TrackingService: Saved new tracking event for package ID {$package->id}", ['event_id' => $trackingEvent->id]);
                    } catch (\Exception $saveException) {
                        Log::channel('tracking')->error("TrackingService: Failed to save tracking event for package ID {$package->id}", [
                            'exception' => $saveException->getMessage(),
                            'trace' => $saveException->getTraceAsString(),
                        ]);
                    }
                } else {
                    Log::channel('tracking')->debug("TrackingService: Skipped duplicate tracking event for package ID {$package->id}");
                }
            }
        }
    }

    private function savePostalOrderEvents(Package $package, array $postalOrderEvents): void
    {
        Log::channel('tracking')->debug("TrackingService: Saving " . count($postalOrderEvents) . " postal order events for package ID {$package->id}");

        try {
            $eventsData = array_map(fn(PostalOrderEventResponseDTO $dto) => $dto->toArray(), $postalOrderEvents);
            $package->updateQuietly(['postal_order_events_data' => $eventsData]);
            Log::channel('tracking')->debug("TrackingService: Saved postal order events for package ID {$package->id}");
        } catch (\Exception $e) {
            Log::channel('tracking')->error("TrackingService: Failed to save postal order events for package ID {$package->id}", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function updatePackageStatus(Package $package, array $historyEvents): void
    {
        if (empty($historyEvents)) {
            Log::channel('tracking')->debug("TrackingService: No history events to update status for package ID {$package->id}");
            return;
        }

        $lastEvent = end($historyEvents);
        $operTypeId = $lastEvent->operationTypeId;
        $operAttrId = $lastEvent->operationAttrId;
        $newStatus = null;

        switch ($operTypeId) {
            // Операции, указывающие на отправку/в пути
            case 1:  // Прием
            case 8:  // Обработка
            case 13: // Регистрация отправки
            case 27: // Получена электронная регистрация
            case 28: // Присвоение идентификатора
            case 32: // Поступление от АПО
            case 12: // В СЦ (сортировочный центр)
            case 33: // Международная обработка
            case 21: // Доставка
            case 31: // Обработка перевозчиком
            case 34: // Электронное уведомление
            case 39: // Таможенное декларирование
            case 40: // Таможенный контроль
            case 41: // Обработка таможенных платежей
                $newStatus = PackageStatus::SENT;
                break;

            // Отправка
            case 3:
                if (in_array($operAttrId, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
                    // Это отправка
                    $newStatus = PackageStatus::SENT;
                } else {
                    // Это возврат
                    $newStatus = PackageStatus::CANCELED;
                }
                break;

            // Операции обработки
            case 7:
                $processingAttrs = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85];
                if (in_array($operAttrId, $processingAttrs)) {
                    $newStatus = PackageStatus::SENT;
                }
                break;

            // Операции хранения
            case 6:
                $storageAttrs = [1, 2, 3, 4, 5, 6, 7];
                if (in_array($operAttrId, $storageAttrs)) {
                    $newStatus = PackageStatus::SENT;
                }
                break;

            // Операции вручения
            case 2:
                $successDeliveryAttrs = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37];
                if (in_array($operAttrId, $successDeliveryAttrs)) {
                    $newStatus = PackageStatus::RECEIVED;
                } else {
                    $newStatus = PackageStatus::SENT; // В процессе вручения
                }
                break;

            case 43: // Вручение разрешено
                $newStatus = PackageStatus::RECEIVED;
                break;

            // Операции возврата/отмены/уничтожения
            case 5:  // Невручение (уничтожение/изъятие)
            case 16: // Уничтожение
            case 17: // Оформление в собственность
            case 18: // Регистрация утраты
            case 26: // Отмена
                $newStatus = PackageStatus::CANCELED;
                break;

            // Операции проблем/ошибок
            case 4:  // Досылка почты
            case 11: // Прием на таможню
            case 14: // Таможенное оформление
            case 22: // Поступление на временное хранение
            case 25: // Вскрытие
            case 35: // Отказ в курьерской доставке
            case 44: // Отказ в приеме
            case 50: // Неудачная доставка в АПС
            case 51: // Неудачная доставка в ПВЗ
            case 69: // Неудачный курьерский сбор
                $newStatus = PackageStatus::SENT; // Проблемы, но посылка ещё "отправлена"
                break;

            default:
                if (!in_array($package->status, [PackageStatus::RECEIVED, PackageStatus::CANCELED])) {
                    // Проверяем, является ли операция одной из "движущих" посылку
                    $movingOperations = [4, 5, 7, 8, 9, 10, 11, 12, 14, 19, 21, 22, 23, 24, 29, 30, 31, 33, 34, 39, 40, 41, 42, 43, 53, 54, 61, 66, 67];
                    if (in_array($operTypeId, $movingOperations)) {
                        $newStatus = PackageStatus::SENT;
                    }
                }
                break;
        }

        if ($newStatus !== null && $newStatus !== $package->status) {
            try {
                $package->updateQuietly(['status' => $newStatus]);
                Log::channel('tracking')->info("TrackingService: Updated status for package ID {$package->id} to " . $newStatus->toString() . " (value: {$newStatus->value})");
            } catch (\Exception $e) {
                Log::channel('tracking')->error("TrackingService: Failed to update status for package ID {$package->id}", [
                    'exception' => $e->getMessage(),
                    'new_status_value' => $newStatus?->value,
                    'new_status_name' => $newStatus?->toString()
                ]);
            }
        } else {
            Log::channel('tracking')->debug("TrackingService: Status for package ID {$package->id} unchanged or update not needed. Current: " . ($package->status?->toString() ?? 'N/A') . " (value: {$package->status?->value})");
        }
    }

    private function saveTrackingError(Package $package, \Exception $exception, ErrorTypeEnum $errorType): void
    {
        try {
            $package->updateQuietly([
                'last_tracking_error' => $exception->getMessage(),
                'last_tracking_error_type' => $errorType->value
            ]);

            Log::channel('tracking')->debug("TrackingService: Saved tracking error for package ID {$package->id}", [
                'error_type' => $errorType->value,
                'error_message' => $exception->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::channel('tracking')->error("TrackingService: Failed to save tracking error for package ID {$package->id}", [
                'exception' => $e->getMessage(),
                'original_error' => $exception->getMessage()
            ]);
        }
    }

    private function canUpdate(Package $package): bool
    {
        $cacheKey = "tracking:last_update:package:{$package->id}";
        $lastUpdate = Cache::get($cacheKey);

        if ($lastUpdate === null) {
            return true;
        }

        $lastUpdateCarbon = \Illuminate\Support\Carbon::parse($lastUpdate);
        $allowedAfter = $lastUpdateCarbon->copy()->addMinutes($this->antiFloodMinutes);

        return now()->isAfter($allowedAfter);
    }

    private function updateLastTrackingAt(Package $package): void
    {
        $cacheKey = "tracking:last_update:package:{$package->id}";
        Cache::put($cacheKey, now()->toISOString(), now()->addMinutes($this->antiFloodMinutes + 1));
    }

    private function updateLastTrackingTimestamp(Package $package): void
    {
        try {
            $package->updateQuietly(['last_tracking_update' => now()]);
            Log::channel('tracking')->debug("TrackingService: Updated last_tracking_update timestamp for package ID {$package->id}");
        } catch (\Exception $e) {
            Log::channel('tracking')->warning("TrackingService: Failed to update last_tracking_update timestamp for package ID {$package->id}", [
                'exception' => $e->getMessage()
            ]);
        }
    }
}

?>
