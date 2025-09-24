<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\AddressDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class AddressesRequest extends AbstractAction
{
    /**
     * Создание нового адреса.
     *
     * @param array $addressData Данные адреса.
     * @return AddressDTO Созданный адрес.
     * @throws ShopogolicApiException
     */
    public function create(array $addressData): AddressDTO
    {
        $response = $this->client->post('addresses', $addressData);
        return $this->mapToDTO($response);
    }

    /**
     * Редактирование существующего адреса.
     *
     * @param int $addressId ID адреса.
     * @param array $addressData Данные для обновления.
     * @return AddressDTO Обновленный адрес (может иметь новый ID, если старый ушел в архив).
     * @throws ShopogolicApiException
     */
    public function update(int $addressId, array $addressData): AddressDTO
    {
        $response = $this->client->put("addresses/{$addressId}", $addressData);
        return $this->mapToDTO($response);
    }

    /**
     * Получение списка адресов с фильтрацией и расширением.
     *
     * @param array $filters Фильтры, например: ['user_id' => 123]
     * @param array $expand Список связанных сущностей для загрузки, например: ['user', 'country']
     * @return array Массив объектов AddressDTO.
     * @throws ShopogolicApiException
     */
    public function getAll(array $filters = [], array $expand = []): array
    {
        $query = $filters;
        if (!empty($expand)) {
            $query['expand'] = implode(',', $expand);
        }
        return $this->getAndMap('addresses', $query);
    }

    /**
     * Получение одного адреса по ID.
     *
     * @param int $addressId ID адреса.
     * @param array $expand Список связанных сущностей для загрузки.
     * @return AddressDTO|null Адрес или null, если не найден.
     * @throws ShopogolicApiException
     */
    public function getById(int $addressId, array $expand = []): ?AddressDTO
    {
        try {
            $query = [];
            if (!empty($expand)) {
                $query['expand'] = implode(',', $expand);
            }
            $response = $this->client->get("addresses/{$addressId}", $query);
            return $this->mapToDTO($response);
        } catch (ShopogolicApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Удаление адреса.
     *
     * @param int $addressId ID адреса.
     * @return bool true в случае успеха (код 204).
     * @throws ShopogolicApiException если удаление не удалось.
     */
    public function delete(int $addressId): bool
    {
        try {
            $this->client->delete("addresses/{$addressId}");
            return true;
        } catch (ShopogolicApiException $e) {
            if ($e->getStatusCode() !== 204) {
                throw $e;
            }
            return true;
        }
    }

    protected function mapToDTO(array $data): AddressDTO
    {
        return new AddressDTO(
            (int) ($data['id'] ?? 0),
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            (string) ($data['country_code'] ?? ''),
            (string) ($data['zipcode'] ?? ''),
            $data['address_line1'] ?? null,
            $data['address_line2'] ?? null,
            $data['region'] ?? null,
            $data['city'] ?? null,
            $data['street'] ?? null,
            $data['house'] ?? null,
            $data['apt'] ?? null,
            (string) ($data['phone'] ?? ''),
            $data['firstname'] ?? null,
            $data['lastname'] ?? null,
            $data['midname'] ?? null,
            $data['passport_series'] ?? null,
            $data['passport_number'] ?? null,
            $data['passport_agency'] ?? null,
            $data['passport_date'] ?? null,
            $data['inn'] ?? null,
            $data['birth'] ?? null,
            $data['email'] ?? null,
            $data['company'] ?? null,
            $data['company_number'] ?? null,
            $data['vat_number'] ?? null,
            $data['user'] ?? null,
            $data['country'] ?? null,
            $data['relatedRegion'] ?? null,
            $data['relatedCity'] ?? null
        );
    }
}
