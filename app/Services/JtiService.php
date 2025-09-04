<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JtiService
{
    protected string $prefix = 'jti:';
    protected int $defaultTtl = 3600; // 1 час

    public function isAlreadyUsed(string $jti, string $provider): bool
    {
        $key = $this->generateKey($jti, $provider);

        if (Cache::has($key)) {
            Log::warning('JTI replay attempt detected', [
                'jti' => $jti,
                'provider' => $provider,
                'ip' => request()->ip()
            ]);
            return true;
        }

        return false;
    }

    public function markAsUsed(string $jti, string $provider, ?int $ttl = null): void
    {
        $key = $this->generateKey($jti, $provider);
        $actualTtl = $ttl ?? $this->defaultTtl;

        Cache::put($key, true, $actualTtl);

        Log::debug('JTI stored in cache', [
            'jti' => $jti,
            'provider' => $provider,
            'ttl_seconds' => $actualTtl,
            'cache_key' => $key
        ]);
    }

    public function generateKey(string $jti, string $provider): string
    {
        return $this->prefix . $provider . ':' . $jti;
    }
}
