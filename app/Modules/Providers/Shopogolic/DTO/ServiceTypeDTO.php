<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class ServiceTypeDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $name_en,
        public string $name_ru,
        public int $type_id,
        public string $type
    ) {}

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['name_en'] ?? ''),
            (string) ($data['name_ru'] ?? ''),
            (int) ($data['type_id'] ?? 0),
            (string) ($data['type'] ?? '')
        );
    }
}
