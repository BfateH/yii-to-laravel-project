<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class CountryDTO extends AbstractDTO
{
    public int $id;
    public string $name_ru;
    public string $name_en;
    public string $code;
    public function __construct(int $id, string $name_ru, string $name_en, string $code)
    {
        $this->id = $id;
        $this->name_ru = $name_ru;
        $this->name_en = $name_en;
        $this->code = $code;
    }
}
