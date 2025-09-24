<?php

namespace App\Modules\Providers;

use Illuminate\Support\Str;

class ProviderFactory
{
    /**
     * Создаёт и возвращает экземпляр провайдера по его имени.
     *
     * @param string $providerName Имя провайдера (например, 'shopogolic')
     * @return ProviderInterface
     * @throws \InvalidArgumentException если провайдер не найден или не реализует интерфейс
     */
    public static function make(string $providerName): ProviderInterface
    {
        $className = 'App\\Modules\\Providers\\' . Str::studly($providerName) . 'Provider';

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Provider class '{$className}' not found.");
        }

        if (!in_array(ProviderInterface::class, class_implements($className))) {
            throw new \InvalidArgumentException("Provider class '{$className}' must implement ProviderInterface.");
        }

        return app($className);
    }
}
