<?php

namespace App\Modules\Currency\Domain;

class Currency
{
    public function __construct(
        private string $code, // Код валюты, например, 'RUB', 'USD'
        private string $name, // Название валюты, например, 'Российский рубль'
    ) {}

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
