<?php

namespace App\Modules\Tracking\Services;

use App\Modules\OrderManagement\Models\Package;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackingCacheService
{
    private const DEFAULT_TTL_MINUTES = 10;

    /**
     *
     * @param Package $package
     * @return string
     */
    public function getTrackingHistoryCacheKey(Package $package): string
    {
        return "tracking:history:package:{$package->id}";
    }

    /**
     *
     * @param Package $package
     * @return string
     */
    public function getPostalOrderEventsCacheKey(Package $package): string
    {
        return "tracking:postal_order:package:{$package->id}";
    }

    /**
     * Получить кэшированную историю отслеживания.
     *
     * @param Package $package
     * @param int $ttlMinutes Время жизни кэша в минутах
     * @return array|null Массив событий или null, если нет в кэше
     */
    public function getTrackingHistory(Package $package, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): ?array
    {
        $key = $this->getTrackingHistoryCacheKey($package);
        $cachedData = Cache::get($key);

        if ($cachedData !== null) {
            Log::channel('tracking')->debug("TrackingCacheService: Cache HIT for tracking history of package ID {$package->id}");
            return $cachedData;
        }

        Log::channel('tracking')->debug("TrackingCacheService: Cache MISS for tracking history of package ID {$package->id}");
        return null;
    }

    /**
     * Сохранить историю отслеживания в кэш.
     *
     * @param Package $package
     * @param array $historyEvents Массив событий (DTO или атрибутов)
     * @param int $ttlMinutes Время жизни кэша в минутах
     */
    public function putTrackingHistory(Package $package, array $historyEvents, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): void
    {
        $key = $this->getTrackingHistoryCacheKey($package);
        Cache::put($key, $historyEvents, now()->addMinutes($ttlMinutes));
        Log::channel('tracking')->debug("TrackingCacheService: Cached tracking history for package ID {$package->id} for {$ttlMinutes} minutes");
    }

    /**
     * Получить кэшированные события наложенного платежа.
     *
     * @param Package $package
     * @param int $ttlMinutes Время жизни кэша в минутах
     * @return array|null Массив событий или null, если нет в кэше
     */
    public function getPostalOrderEvents(Package $package, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): ?array
    {
        $key = $this->getPostalOrderEventsCacheKey($package);
        $cachedData = Cache::get($key);

        if ($cachedData !== null) {
            Log::channel('tracking')->debug("TrackingCacheService: Cache HIT for postal order events of package ID {$package->id}");
            return $cachedData;
        }

        Log::channel('tracking')->debug("TrackingCacheService: Cache MISS for postal order events of package ID {$package->id}");
        return null;
    }

    /**
     * Сохранить события наложенного платежа в кэш.
     *
     * @param Package $package
     * @param array $postalOrderEvents Массив событий
     * @param int $ttlMinutes Время жизни кэша в минутах
     */
    public function putPostalOrderEvents(Package $package, array $postalOrderEvents, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): void
    {
        $key = $this->getPostalOrderEventsCacheKey($package);
        Cache::put($key, $postalOrderEvents, now()->addMinutes($ttlMinutes));
        Log::channel('tracking')->debug("TrackingCacheService: Cached postal order events for package ID {$package->id} for {$ttlMinutes} minutes");
    }

    /**
     * Очистить кэш для конкретной посылки.
     *
     * @param Package $package
     */
    public function forget(Package $package): void
    {
        $historyKey = $this->getTrackingHistoryCacheKey($package);
        $postalOrderKey = $this->getPostalOrderEventsCacheKey($package);

        Cache::forget($historyKey);
        Cache::forget($postalOrderKey);
        Log::channel('tracking')->debug("TrackingCacheService: Cleared cache for package ID {$package->id}");
    }
}
?>
