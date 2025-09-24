<?php

namespace App\Modules\Providers\Shopogolic\Actions;

use App\Modules\Providers\Shopogolic\DTO\CalculationResultDTO;
use App\Modules\Providers\Shopogolic\DTO\CourierDTO;
use App\Modules\Providers\Shopogolic\Exceptions\ShopogolicApiException;

class CalculatorRequest extends AbstractAction
{
    /**
     * Рассчитывает стоимость доставки для заданных параметров.
     *
     * @param array $params Параметры: warehouse_id, weight, country_code, [length, width, height]
     * @return CalculationResultDTO
     * @throws ShopogolicApiException
     */
    public function calculate(array $params): CalculationResultDTO
    {
        if (empty($params['warehouse_id']) || empty($params['weight']) || empty($params['country_code'])) {
            throw new \InvalidArgumentException('Required parameters: warehouse_id, weight, country_code');
        }

        $response = $this->client->post('parcels/rate', $params);

        $weight = (float) ($response['weight'] ?? $params['weight']);
        $length = (float) ($response['length'] ?? ($params['length'] ?? 0.0));
        $width = (float) ($response['width'] ?? ($params['width'] ?? 0.0));
        $height = (float) ($response['height'] ?? ($params['height'] ?? 0.0));

        $couriers = [];
        if (isset($response['couriers']) && is_array($response['couriers'])) {
            foreach ($response['couriers'] as $courierData) {
                $couriers[] = $this->mapCourierToDTO($courierData);
            }
        }

        return new CalculationResultDTO($weight, $length, $width, $height, $couriers);
    }

    /**
     * @param array $data Сырые данные курьера от API
     * @return CourierDTO
     */
    private function mapCourierToDTO(array $data): CourierDTO
    {
        return new CourierDTO(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? 'Unknown Courier'),
            (int) ($data['warehouse_id'] ?? 0),
            (float) ($data['calculated_price'] ?? 0.0)
        );
    }

    /**
     * Этот метод не используется в CalculatorRequest, так как результат расчёта — не список однотипных DTO.
     * Реализован для удовлетворения абстрактного контракта AbstractAction.
     *
     * @param array $data
     * @return object|array
     */
    protected function mapToDTO(array $data): object|array
    {
        throw new \BadMethodCallException('Method mapToDTO should not be called directly in CalculatorRequest. Use calculate() instead.');
    }
}
