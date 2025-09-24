<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class ServiceDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $date_created,
        public ?string $date_performed,
        public int $service_type_id,
        public int $status_id,
        public string $status,
        public ?string $comment,
        public ?ServiceTypeDTO $serviceType
    ) {}

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $serviceType = null;
        if (!empty($data['serviceType'])) {
            $serviceType = ServiceTypeDTO::fromArray($data['serviceType']);
        }

        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['date_created'] ?? ''),
            $data['date_performed'] ?? null,
            (int) ($data['service_type_id'] ?? 0),
            (int) ($data['status_id'] ?? 0),
            (string) ($data['status'] ?? 'Unknown'),
            $data['comment'] ?? null,
            $serviceType
        );
    }
}
