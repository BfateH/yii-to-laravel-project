<?php

namespace App\Modules\Currency\Domain;

use DateTimeImmutable;

class ExchangeRate
{
    public function __construct(
        private string $baseCurrencyCode, // Код базовой валюты
        private string $targetCurrencyCode, // Код целевой валюты
        private float $rate, // Курс
        private DateTimeImmutable $date, // Дата, на которую действует курс
    ) {}

    public function getBaseCurrencyCode(): string
    {
        return $this->baseCurrencyCode;
    }

    public function getTargetCurrencyCode(): string
    {
        return $this->targetCurrencyCode;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    // Метод для удобства, если нужно получить ключ для кэширования или поиска
    public function getId(): string
    {
        return $this->baseCurrencyCode . '_' . $this->targetCurrencyCode . '_' . $this->date->format('Y-m-d');
    }
}
