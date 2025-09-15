<?php

namespace App\Modules\Currency\Infrastructure\Factories;

use App\Modules\Currency\Domain\ExchangeRatesProviderInterface;
use App\Modules\Currency\Infrastructure\Providers\CBRProvider;
use InvalidArgumentException;

class ExchangeRateProviderFactory
{
    private array $providers = [
        'cbr' => CBRProvider::class,
        // Сюда можно будет добавлять другие провайдеры
    ];

    public function create(string $providerName): ExchangeRatesProviderInterface
    {
        if (!isset($this->providers[$providerName])) {
            throw new InvalidArgumentException("Неизвестный провайдер курсов валют: {$providerName}");
        }

        $providerClass = $this->providers[$providerName];

        return new $providerClass();
    }
}
