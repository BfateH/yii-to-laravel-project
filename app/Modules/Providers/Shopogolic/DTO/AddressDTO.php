<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class AddressDTO extends AbstractDTO
{
    public function __construct(
        public int $id,
        public ?int $user_id,
        public string $country_code,
        public string $zipcode,
        public ?string $address_line1,
        public ?string $address_line2,
        public ?string $region,
        public ?string $city,
        public ?string $street,
        public ?string $house,
        public ?string $apt,
        public string $phone,

        public ?string $firstname = null,
        public ?string $lastname = null,
        public ?string $midname = null,
        public ?string $passport_series = null,
        public ?string $passport_number = null,
        public ?string $passport_agency = null,
        public ?string $passport_date = null,
        public ?string $inn = null,
        public ?string $birth = null,
        public ?string $email = null,
        public ?string $company = null,
        public ?string $company_number = null,
        public ?string $vat_number = null,

        public ?array $user = null,
        public ?array $country = null,
        public ?array $relatedRegion = null,
        public ?array $relatedCity = null
    ) {}

    public function getFullAddress(): string
    {
        $parts = [];

        if ($this->address_line1) {
            $parts[] = $this->address_line1;
        }
        if ($this->address_line2) {
            $parts[] = $this->address_line2;
        }
        if ($this->street && $this->house) {
            $parts[] = "{$this->street}, {$this->house}";
        } elseif ($this->street) {
            $parts[] = $this->street;
        } elseif ($this->house) {
            $parts[] = "д. {$this->house}";
        }
        if ($this->apt) {
            $parts[] = "кв. {$this->apt}";
        }
        if ($this->city) {
            $parts[] = $this->city;
        }
        if ($this->region) {
            $parts[] = $this->region;
        }
        if ($this->zipcode) {
            $parts[] = $this->zipcode;
        }

        return implode(', ', $parts);
    }
}
