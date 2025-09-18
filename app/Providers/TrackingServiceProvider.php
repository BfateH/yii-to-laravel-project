<?php

namespace App\Providers;

use App\Modules\Tracking\Contracts\TrackingProviderInterface;
use App\Modules\Tracking\Providers\RussianPost\RussianPostClient;
use App\Modules\Tracking\Providers\RussianPost\RussianPostProvider;
use App\Modules\Tracking\Services\TrackingCacheService;
use App\Modules\Tracking\Services\TrackingRateLimitService;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RussianPostClient::class, function ($app) {
            $login = env('RUSPOST_CLIENT_ID');
            $password = env('RUSPOST_CLIENT_SECRET');

            if (empty($login) || empty($password)) {
                throw new \InvalidArgumentException('Russian Post API credentials (RUSPOST_CLIENT_ID, RUSPOST_CLIENT_SECRET) are not set in .env');
            }

            return new RussianPostClient($login, $password);
        });

        $this->app->bind(TrackingProviderInterface::class, function ($app) {
            $client = $app->make(RussianPostClient::class);
            return new RussianPostProvider($client);
        });

        $this->app->bind(TrackingRateLimitService::class, function ($app) {
            return new TrackingRateLimitService();
        });

        $this->app->bind(TrackingCacheService::class, function ($app) {
            return new TrackingCacheService();
        });

        $this->app->bind(TrackingService::class, function ($app) {
            $provider = $app->make(TrackingProviderInterface::class);
            $rateLimitService = $app->make(TrackingRateLimitService::class);
            $cacheService = $app->make(TrackingCacheService::class);
            return new TrackingService($provider, $rateLimitService, $cacheService);
        });
    }

    public function boot(): void
    {
        //
    }
}

?>
