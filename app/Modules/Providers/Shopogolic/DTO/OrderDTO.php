<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class OrderDTO extends AbstractDTO
{
    /**
     * ID заказа.
     *
     * @var int
     */
    public int $id;

    /**
     * Название заказа (если есть).
     *
     * @var string|null
     */
    public ?string $name;

    /**
     * ID склада, к которому привязан заказ.
     *
     * @var int
     */
    public int $warehouse_id;

    /**
     * ID пользователя, создавшего заказ.
     *
     * @var int
     */
    public int $user_id;

    /**
     * Статус заказа (числовой код).
     *
     * @var int
     */
    public int $status_id;

    /**
     * Название статуса (например, 'Draft', 'Completed').
     *
     * @var string
     */
    public string $status;

    /**
     * Дата создания заказа.
     *
     * @var string|null
     */
    public ?string $date_created;

    /**
     * Дата поступления на склад.
     *
     * @var string|null
     */
    public ?string $date_received;

    /**
     * Трек-номер входящей посылки (если тип MF).
     *
     * @var string|null
     */
    public ?string $track;

    /**
     * Название магазина.
     *
     * @var string|null
     */
    public ?string $shop_name;

    /**
     * Массив товаров в заказе.
     *
     * @var ItemDTO[]|null
     */
    public ?array $items;

    /**
     * Массив услуг, прикрепленных к заказу.
     *
     * @var ServiceDTO[]|null
     */
    public ?array $services;

    /**
     * @param int $id
     * @param string|null $name
     * @param int $warehouse_id
     * @param int $user_id
     * @param int $status_id
     * @param string $status
     * @param string|null $date_created
     * @param string|null $date_received
     * @param string|null $track
     * @param string|null $shop_name
     * @param ItemDTO[]|null $items
     * @param ServiceDTO[]|null $services
     */
    public function __construct(
        int $id,
        ?string $name,
        int $warehouse_id,
        int $user_id,
        int $status_id,
        string $status,
        ?string $date_created = null,
        ?string $date_received = null,
        ?string $track = null,
        ?string $shop_name = null,
        ?array $items = null,
        ?array $services = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->warehouse_id = $warehouse_id;
        $this->user_id = $user_id;
        $this->status_id = $status_id;
        $this->status = $status;
        $this->date_created = $date_created;
        $this->date_received = $date_received;
        $this->track = $track;
        $this->shop_name = $shop_name;
        $this->items = $items;
        $this->services = $services;
    }
}
