<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class ParcelItemDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $description,
        public ?string $descr_ru,
        public ?string $descr_en,
        public int $qty,
        public float $cost,
        public string $sku,
        public string $url,
        public ?string $type,
        public ?string $brand,
        public ?string $color_size,
        public ?int $hscode_id
    ) {}

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['description'] ?? ''),
            $data['descr_ru'] ?? null,
            $data['descr_en'] ?? null,
            (int) ($data['qty'] ?? 1),
            (float) ($data['cost'] ?? 0.0),
            (string) ($data['sku'] ?? ''),
            (string) ($data['url'] ?? ''),
            $data['type'] ?? null,
            $data['brand'] ?? null,
            $data['color_size'] ?? null,
            isset($data['hscode_id']) ? (int) $data['hscode_id'] : null
        );
    }
}
