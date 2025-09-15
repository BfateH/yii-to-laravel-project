<?php

namespace App\Modules\Currency\Domain;

use DateTimeInterface;

interface ExchangeRateRepositoryInterface
{
    /**
     * Сохраняет курс валюты.
     *
     * @param ExchangeRate $rate Курс для сохранения.
     */
    public function save(ExchangeRate $rate): void;

    /**
     * Находит курс на конкретную дату для пары валют.
     *
     * @param DateTimeInterface $date Дата.
     * @param string $baseCurrencyCode Код базовой валюты.
     * @param string $targetCurrencyCode Код целевой валюты.
     * @return ExchangeRate|null Найденный курс или null.
     */
    public function findByDateAndCurrencies(DateTimeInterface $date, string $baseCurrencyCode, string $targetCurrencyCode): ?ExchangeRate;

    /**
     * Получает исторические курсы для пары валют в заданном диапазоне дат.
     *
     * @param string $baseCurrencyCode Код базовой валюты.
     * @param string $targetCurrencyCode Код целевой валюты.
     * @param DateTimeInterface $startDate Начальная дата.
     * @param DateTimeInterface $endDate Конечная дата.
     * @return ExchangeRate[] Массив курсов.
     */
    public function findHistoricalRates(string $baseCurrencyCode, string $targetCurrencyCode, DateTimeInterface $startDate, DateTimeInterface $endDate): array;
}
