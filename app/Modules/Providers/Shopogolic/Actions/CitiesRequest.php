<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\CityDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class CitiesRequest extends AbstractAction
{
    /**
     * Получение списка городов с фильтрацией.
     *
     * @param array $filters Фильтры: ['name' => 'like', 'region_id' => 123]
     * @param array $expand Список связанных сущностей для загрузки, например: ['country', 'region']
     * @param int $page Номер страницы
     * @return array Массив объектов CityDTO.
     * @throws ShopogolicApiException
     */
    public function getAll(array $filters = [], array $expand = [], int $page = 1): array
    {
        $query = [
            'page' => $page,
        ];

        if (!empty($filters['name'])) {
            $query['filter[name][like]'] = $filters['name'];
        }
        if (!empty($filters['region_id'])) {
            $query['filter[region_id]'] = $filters['region_id'];
        }

        if (!empty($expand)) {
            $query['expand'] = implode(',', $expand);
        }

        return $this->getAndMap('/cities', $query);
    }

    protected function mapToDTO(array $data): CityDTO
    {
        return new CityDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            $data['prefix'] ?? null,
            (int) ($data['country_id'] ?? 0),
            isset($data['region_id']) ? (int) $data['region_id'] : null,
            $data['country'] ?? null,
            $data['region'] ?? null
        );
    }
}
