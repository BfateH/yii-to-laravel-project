<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class ItemDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $description,
        public int $qty,
        public float $price,
        public ?float $delivery,
        public string $sku,
        public string $url,
        public ?string $size,
        public ?string $color,
        public ?string $comment
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
            (int) ($data['qty'] ?? 1),
            (float) ($data['price'] ?? 0.0),
            isset($data['delivery']) ? (float) $data['delivery'] : null,
            (string) ($data['sku'] ?? ''),
            (string) ($data['url'] ?? ''),
            $data['size'] ?? null,
            $data['color'] ?? null,
            $data['comment'] ?? null
        );
    }
}
