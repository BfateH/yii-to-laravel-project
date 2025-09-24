<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\HsCodeDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class HsCodesRequest extends AbstractAction
{
    /**
     * Получение полного списка таможенных кодов.
     *
     * @param int $perPage Количество кодов на странице (макс. 50)
     * @param int $page Номер страницы
     * @return array Массив объектов HsCodeDTO.
     * @throws ShopogolicApiException
     */
    public function getAll(int $perPage = 20, int $page = 1): array
    {
        $query = [
            'per-page' => min($perPage, 50),
            'page' => $page,
        ];

        return $this->getAndMap('/hscode', $query);
    }

    protected function mapToDTO(array $data): HsCodeDTO
    {
        return new HsCodeDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['code'] ?? ''),
            (string) ($data['name_ru'] ?? ''),
            (string) ($data['name_en'] ?? '')
        );
    }
}
