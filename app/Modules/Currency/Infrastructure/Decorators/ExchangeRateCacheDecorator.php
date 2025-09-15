<?php

namespace App\Modules\Currency\Infrastructure\Decorators;

use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

class ExchangeRateCacheDecorator implements ExchangeRateRepositoryInterface
{
    public function __construct(
        private ExchangeRateRepositoryInterface $repository,
        private int $ttlMinutes = 60 // Время жизни кэша в минутах
    ) {}

    public function save(ExchangeRate $rate): void
    {
        // Сохраняем в основном репозитории
        $this->repository->save($rate);
        // Инвалидируем кэш для этого курса
        Cache::forget($this->getCacheKey($rate->getBaseCurrencyCode(), $rate->getTargetCurrencyCode(), $rate->getDate()));
    }

    public function findByDateAndCurrencies(DateTimeInterface $date, string $baseCurrencyCode, string $targetCurrencyCode): ?ExchangeRate
    {
        $cacheKey = $this->getCacheKey($baseCurrencyCode, $targetCurrencyCode, $date);

        return Cache::remember($cacheKey, $this->ttlMinutes * 60, function () use ($date, $baseCurrencyCode, $targetCurrencyCode) {
            return $this->repository->findByDateAndCurrencies($date, $baseCurrencyCode, $targetCurrencyCode);
        });
    }

    public function findHistoricalRates(string $baseCurrencyCode, string $targetCurrencyCode, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $cacheKey = "historical_rates_{$baseCurrencyCode}_{$targetCurrencyCode}_" . $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d');

        return Cache::remember($cacheKey, $this->ttlMinutes * 30, function () use ($baseCurrencyCode, $targetCurrencyCode, $startDate, $endDate) {
            return $this->repository->findHistoricalRates($baseCurrencyCode, $targetCurrencyCode, $startDate, $endDate);
        });
    }

    private function getCacheKey(string $baseCurrencyCode, string $targetCurrencyCode, DateTimeInterface $date): string
    {
        return "exchange_rate_{$baseCurrencyCode}_{$targetCurrencyCode}_" . $date->format('Y-m-d');
    }
}
