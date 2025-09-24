<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class UserDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public string $email,
        public ?string $external_id,
        public string $name,
        public ?string $firstname = null,
        public ?string $lastname = null,
        public ?string $midname = null,
        public ?string $phone = null,
        public ?string $language = null,
        public ?string $country_code = null
    ) {}

    public function getFullName(): string
    {
        return trim("{$this->firstname} {$this->midname} {$this->lastname}");
    }
}
