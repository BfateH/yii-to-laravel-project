<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class CourierDTO extends AbstractDTO
{
    /**
     * ID способа доставки.
     *
     * @var int
     */
    public int $id;

    /**
     * Название способа доставки.
     *
     * @var string
     */
    public string $name;

    /**
     * ID склада, к которому привязан этот способ доставки.
     *
     * @var int
     */
    public int $warehouse_id;

    /**
     * Рассчитанная цена доставки (может быть 0, если расчёт не производился).
     *
     * @var float
     */
    public float $calculated_price;

    /**
     * @param int $id
     * @param string $name
     * @param int $warehouse_id
     * @param float $calculated_price
     */
    public function __construct(int $id, string $name, int $warehouse_id, float $calculated_price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->warehouse_id = $warehouse_id;
        $this->calculated_price = $calculated_price;
    }
}
