<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\RegionDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class RegionsRequest extends AbstractAction
{
    /**
     * Получение списка регионов с фильтрацией.
     *
     * @param array $filters Фильтры: ['name' => 'like', 'country_code' => 'RU']
     * @param array $expand Список связанных сущностей для загрузки, например: ['country']
     * @param int $page Номер страницы
     * @return array Массив объектов RegionDTO.
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
        if (!empty($filters['country_code'])) {
            $query['filter[country_code]'] = $filters['country_code'];
        }

        if (!empty($expand)) {
            $query['expand'] = implode(',', $expand);
        }

        return $this->getAndMap('regions', $query);
    }

    protected function mapToDTO(array $data): RegionDTO
    {
        return new RegionDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? ''),
            $data['prefix'] ?? null,
            $data['suffix'] ?? null,
            (int) ($data['country_id'] ?? 0),
            $data['country'] ?? null
        );
    }
}
