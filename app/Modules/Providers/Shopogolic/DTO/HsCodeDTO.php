<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class HsCodeDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name_ru,
        public string $name_en
    ) {}
}
