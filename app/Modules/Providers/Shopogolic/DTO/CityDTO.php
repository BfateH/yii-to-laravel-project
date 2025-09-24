<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class CityDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $prefix,
        public int $country_id,
        public ?int $region_id,
        public ?array $country = null,
        public ?array $region = null
    ) {}
}
