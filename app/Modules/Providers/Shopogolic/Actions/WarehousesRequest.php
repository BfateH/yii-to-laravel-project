<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\CountryDTO;
use App\Modules\Providers\Shopogolic\DTO\WarehouseDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class WarehousesRequest extends AbstractAction
{
    /**
     * @var array<int, CountryDTO>|null
     */
    private ?array $countryCache = null;

    /**
     * Получение списка всех складов.
     *
     * @return array Массив объектов WarehouseDTO
     * @throws ShopogolicApiException
     */
    public function getAll(): array
    {
        if ($this->countryCache === null) {
            $this->loadCountryCache();
        }

        return $this->getAndMap('/warehouses');
    }

    /**
     * @param array $data Сырые данные склада от API
     * @return WarehouseDTO
     */
    protected function mapToDTO(array $data): WarehouseDTO
    {
        $id = (int) ($data['id'] ?? 0);
        $countryId = (int) ($data['country_id'] ?? 0);
        $currency = (string) ($data['currency'] ?? 'USD');

        $countryCode = 'XX';
        if ($this->countryCache !== null && isset($this->countryCache[$countryId])) {
            $countryCode = $this->countryCache[$countryId]->code;
        }

        $name = "Warehouse #{$id} ({$countryCode})";

        return new WarehouseDTO($id, $name, $countryCode, $currency);
    }

    /**
     * @return void
     * @throws ShopogolicApiException
     */
    private function loadCountryCache(): void
    {
        $countriesRequest = new CountriesRequest($this->client);
        $countries = $countriesRequest->getAll();

        $this->countryCache = [];
        foreach ($countries as $country) {
            $this->countryCache[$country->id] = $country;
        }
    }
}
