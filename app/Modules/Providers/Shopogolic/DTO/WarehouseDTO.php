<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class WarehouseDTO extends AbstractDTO
{
    /**
     * ID склада.
     * @var int
     */
    public int $id;

    /**
     * Название склада (если есть, иначе генерируем из ID и страны).
     * @var string
     */
    public string $name;

    /**
     * Код страны склада (ISO 3166-1 alpha-2).
     * @var string
     */
    public string $country_code;

    /**
     * Код валюты склада (ISO 4217).
     * @var string
     */
    public string $currency;

    /**
     * @param int $id
     * @param string $name
     * @param string $country_code
     * @param string $currency
     */
    public function __construct(int $id, string $name, string $country_code, string $currency)
    {
        $this->id = $id;
        $this->name = $name;
        $this->country_code = $country_code;
        $this->currency = $currency;
    }
}
