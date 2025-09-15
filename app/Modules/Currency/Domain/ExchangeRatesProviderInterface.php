<?php

namespace App\Modules\Currency\Domain;

use DateTimeInterface;

interface ExchangeRatesProviderInterface
{
    /**
     * Получает курсы валют на заданную дату.
     *
     * @param DateTimeInterface $date Дата, на которую нужно получить курсы.
     * @return ExchangeRate[] Массив объектов ExchangeRate.
     */
    public function getRates(DateTimeInterface $date): array;

    /**
     * Возвращает уникальное имя провайдера.
     *
     * @return string Имя провайдера (например, 'cbr').
     */
    public function getName(): string;
}
