<?php

namespace App\Modules\Tracking\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackingRateLimitService
{
    private const DEFAULT_LIMIT = 100;
    private const DEFAULT_WINDOW_SECONDS = 3600;

    public function isLimitExceeded(string $key, ?int $limit = null, ?int $windowInSeconds = null): bool
    {
        $limit = $limit ?? self::DEFAULT_LIMIT;
        $windowInSeconds = $windowInSeconds ?? self::DEFAULT_WINDOW_SECONDS;
        $dbKey = $this->getDbKey($key);

        try {
            $now = Carbon::now();
            $expiresAt = $now->copy()->addSeconds($windowInSeconds);

            return DB::transaction(function () use ($dbKey, $limit, $expiresAt, $now) {
                // Попытка обновить существующую запись, если она не истекла и лимит не превышен
                $updated = DB::table('tracking_rate_limits')
                    ->where('key', $dbKey)
                    ->where('expires_at', '>', $now)
                    ->where('count', '<', $limit)
                    ->update([
                        'count' => DB::raw('count + 1'),
                    ]);

                if ($updated > 0) {
                    Log::channel('tracking')->debug("TrackingRateLimitService (DB): Incremented count for key '{$dbKey}'. Limit OK.");
                    return false;
                }

                $existingRecord = DB::table('tracking_rate_limits')
                    ->where('key', $dbKey)
                    ->first();

                if ($existingRecord && Carbon::parse($existingRecord->expires_at)->isFuture()) {
                    Log::channel('tracking')->warning("TrackingRateLimitService (DB): Rate limit EXCEEDED for key '{$dbKey}'. Current: {$existingRecord->count}, Limit: {$limit}");
                    return true;
                }

                $values = [
                    'key' => $dbKey,
                    'count' => 1,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now,
                ];

                if ($existingRecord) {
                    DB::table('tracking_rate_limits')
                        ->where('key', $dbKey)
                        ->update($values);
                } else {
                    $values['created_at'] = $now;
                    DB::table('tracking_rate_limits')->insert($values);
                }

                Log::channel('tracking')->debug("TrackingRateLimitService (DB): Created/Reset count for key '{$dbKey}'. Limit OK.");
                return false;
            });

        } catch (\Exception $e) {
            Log::channel('tracking')->error("TrackingRateLimitService (DB): Database error. Allowing request (fail-open). Key: '{$dbKey}'", [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getLimitInfo(string $key, ?int $limit = null, ?int $windowInSeconds = null): array
    {
        $limit = $limit ?? self::DEFAULT_LIMIT;
        $windowInSeconds = $windowInSeconds ?? self::DEFAULT_WINDOW_SECONDS;
        $dbKey = $this->getDbKey($key);

        try {
            $record = DB::table('tracking_rate_limits')->where('key', $dbKey)->first();

            if (!$record) {
                return [
                    'key' => $key,
                    'limit' => $limit,
                    'window_seconds' => $windowInSeconds,
                    'current' => 0,
                    'remaining' => $limit,
                    'reset_time' => now()->addSeconds($windowInSeconds)->toISOString(),
                    'ttl' => $windowInSeconds
                ];
            }

            $expiresAt = Carbon::parse($record->expires_at);
            $now = Carbon::now();

            if ($expiresAt->isPast()) {
                return [
                    'key' => $key,
                    'limit' => $limit,
                    'window_seconds' => $windowInSeconds,
                    'current' => 0,
                    'remaining' => $limit,
                    'reset_time' => now()->toISOString(),
                    'ttl' => 0
                ];
            }

            $current = (int) $record->count;
            $remaining = max(0, $limit - $current);
            $ttl = $expiresAt->diffInSeconds($now);

            return [
                'key' => $key,
                'limit' => $limit,
                'window_seconds' => $windowInSeconds,
                'current' => $current,
                'remaining' => $remaining,
                'reset_time' => $expiresAt->toISOString(),
                'ttl' => $ttl
            ];

        } catch (\Exception $e) {
            Log::channel('tracking')->error("TrackingRateLimitService (DB): Error getting limit info for key '{$dbKey}'", [
                'exception' => $e->getMessage()
            ]);
            return [
                'key' => $key,
                'limit' => $limit,
                'window_seconds' => $windowInSeconds,
                'current' => null,
                'remaining' => null,
                'reset_time' => null,
                'ttl' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    public function resetLimit(string $key): void
    {
        $dbKey = $this->getDbKey($key);
        try {
            DB::table('tracking_rate_limits')->where('key', $dbKey)->delete();
            Log::channel('tracking')->info("TrackingRateLimitService (DB): Reset limit for key '{$dbKey}'");
        } catch (\Exception $e) {
            Log::channel('tracking')->error("TrackingRateLimitService (DB): Error resetting limit for key '{$dbKey}'", [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function getDbKey(string $key): string
    {
        return Str::slug($key, '_');
    }
}
?>
