<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\CountryDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class CountriesRequest extends AbstractAction
{
    /**
     * Получение списка всех стран.
     *
     * @param int $perPage Количество стран на странице (макс. 50)
     * @param int $page Номер страницы
     * @return array Массив объектов CountryDTO.
     * @throws ShopogolicApiException
     */
    public function getAll(int $perPage = 20, int $page = 1): array
    {
        $query = [
            'per-page' => min($perPage, 50),
            'page' => $page,
        ];
        return $this->getAndMap('countries', $query);
    }

    protected function mapToDTO(array $data): CountryDTO
    {
        return new CountryDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['name_ru'] ?? ''),
            (string) ($data['name_en'] ?? ''),
            (string) ($data['code'] ?? '')
        );
    }
}
