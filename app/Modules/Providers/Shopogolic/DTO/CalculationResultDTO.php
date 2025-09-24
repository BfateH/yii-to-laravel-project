<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class CalculationResultDTO extends AbstractDTO
{
    /**
     * Вес посылки, кг.
     *
     * @var float
     */
    public float $weight;

    /**
     * Длина, см.
     *
     * @var float
     */
    public float $length;

    /**
     * Ширина, см.
     *
     * @var float
     */
    public float $width;

    /**
     * Высота, см.
     *
     * @var float
     */
    public float $height;

    /**
     * Массив рассчитанных способов доставки (CourierDTO с заполненным calculated_price).
     *
     * @var array<int, CourierDTO>
     */
    public array $couriers;

    /**
     * @param float $weight
     * @param float $length
     * @param float $width
     * @param float $height
     * @param array<int, CourierDTO> $couriers
     */
    public function __construct(float $weight, float $length, float $width, float $height, array $couriers)
    {
        $this->weight = $weight;
        $this->length = $length;
        $this->width = $width;
        $this->height = $height;
        $this->couriers = $couriers;
    }
}
