<?php

namespace App\Providers;

use App\Modules\Currency\Application\ExchangeRateService;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use App\Modules\Currency\Domain\ExchangeRatesProviderInterface;
use App\Modules\Currency\Infrastructure\Decorators\ExchangeRateCacheDecorator;
use App\Modules\Currency\Infrastructure\Factories\ExchangeRateProviderFactory;
use App\Modules\Currency\Infrastructure\Repositories\DatabaseExchangeRateRepository;
use Illuminate\Support\ServiceProvider;

class CurrencyModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Регистрируем фабрику провайдеров
        $this->app->singleton(ExchangeRateProviderFactory::class);

        // Регистрируем конкретный провайдер на основе конфигурации
        $this->app->singleton(ExchangeRatesProviderInterface::class, function ($app) {
            $factory = $app->make(ExchangeRateProviderFactory::class);
            $providerName = config('currency.default_provider', 'cbr');
            return $factory->create($providerName);
        });

        // Регистрируем репозиторий
        $this->app->singleton(ExchangeRateRepositoryInterface::class, function ($app) {
            $baseRepository = $app->make(DatabaseExchangeRateRepository::class);
            $ttlMinutes = config('currency.cache_ttl', 60);
            return new ExchangeRateCacheDecorator($baseRepository, $ttlMinutes);
        });

        // Регистрируем основной сервис
        $this->app->singleton(ExchangeRateService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/currency.php', 'currency');
    }
}
