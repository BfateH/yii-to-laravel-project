<?php

namespace App\Modules\Currency\Infrastructure\Repositories;

use App\Models\ExchangeRateModel;
use App\Modules\Currency\Domain\ExchangeRate;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use DateTimeInterface;

class DatabaseExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function save(ExchangeRate $rate): void
    {
        ExchangeRateModel::query()->updateOrInsert(
            [
                'base_currency_code' => $rate->getBaseCurrencyCode(),
                'target_currency_code' => $rate->getTargetCurrencyCode(),
                'date' => $rate->getDate(),
            ],
            [
                'rate' => $rate->getRate(),
                'updated_at' => now(),
            ]
        );
    }

    public function findByDateAndCurrencies(DateTimeInterface $date, string $baseCurrencyCode, string $targetCurrencyCode): ?ExchangeRate
    {
        $model = ExchangeRateModel::where('base_currency_code', $baseCurrencyCode)
            ->where('target_currency_code', $targetCurrencyCode)
            ->whereDate('date', $date)
            ->first();

        if (!$model) {
            return null;
        }

        return new ExchangeRate(
            $model->base_currency_code,
            $model->target_currency_code,
            (float) $model->rate,
            new \DateTimeImmutable($model->date->format('Y-m-d'))
        );
    }

    public function findHistoricalRates(string $baseCurrencyCode, string $targetCurrencyCode, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $models = ExchangeRateModel::where('base_currency_code', $baseCurrencyCode)
            ->where('target_currency_code', $targetCurrencyCode)
            ->whereDate('date', '>=', $startDate->format('Y-m-d'))
            ->whereDate('date', '<=', $endDate->format('Y-m-d'))
            ->orderBy('date')
            ->get();

        $rates = [];
        foreach ($models as $model) {
            $rates[] = new ExchangeRate(
                $model->base_currency_code,
                $model->target_currency_code,
                (float) $model->rate,
                new \DateTimeImmutable($model->date->format('Y-m-d'))
            );
        }

        return $rates;
    }
}
