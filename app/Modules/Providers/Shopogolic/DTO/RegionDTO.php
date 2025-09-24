<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class RegionDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $prefix,
        public ?string $suffix,
        public int $country_id,
        public ?array $country = null
    ) {}
}
