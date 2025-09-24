<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class CouriersRequest extends AbstractAction
{
    /**
     * Получение списка курьеров (способов доставки) для указанного склада.
     *
     * @param int $warehouseId ID склада
     * @return array Массив объектов CourierDTO
     * @throws ShopogolicApiException
     */
    public function getByWarehouseId(int $warehouseId): array
    {
        $query = [
            'filter[warehouse_id]' => $warehouseId,
        ];

        return $this->getAndMap('/couriers', $query);
    }

    /**
     * @param array $data Сырые данные курьера от API
     * @return CourierDTO
     */
    protected function mapToDTO(array $data): CourierDTO
    {
        return new CourierDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? 'Unknown Courier'),
            (int) ($data['warehouse_id'] ?? 0),
            (float) ($data['calculated_price'] ?? 0.0)
        );
    }
}
