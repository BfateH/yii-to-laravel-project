<?php

namespace App\Modules\Providers\Shopogolic\DTO;

class ParcelDTO extends AbstractDTO
{
    /**
     * ID посылки.
     *
     * @var int
     */
    public int $id;

    /**
     * Трек-номер посылки.
     *
     * @var string|null
     */
    public ?string $track;

    /**
     * ID склада отправления.
     *
     * @var int
     */
    public int $warehouse_id;

    /**
     * ID курьера (способа доставки).
     *
     * @var int
     */
    public int $courier_id;

    /**
     * ID пользователя-получателя.
     *
     * @var int
     */
    public int $user_id;

    /**
     * ID адреса доставки.
     *
     * @var int
     */
    public int $address_id;

    /**
     * Статус посылки (числовой код).
     *
     * @var int
     */
    public int $status_id;

    /**
     * Название статуса (например, 'New', 'Sent', 'Delivered').
     *
     * @var string
     */
    public string $status;

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
     * Нужна ли страховка (0 = нет, 1 = да).
     *
     * @var int
     */
    public int $insurance;

    /**
     * Комментарий к посылке.
     *
     * @var string|null
     */
    public ?string $comment;

    /**
     * Дата создания посылки.
     *
     * @var string|null
     */
    public ?string $date_created;

    /**
     * Дата отправки посылки.
     *
     * @var string|null
     */
    public ?string $date_sent;

    /**
     * Массив содержимого посылки (декларация).
     *
     * @var ParcelItemDTO[]|null
     */
    public ?array $items;

    /**
     * Массив услуг, прикрепленных к посылке.
     *
     * @var ServiceDTO[]|null
     */
    public ?array $services;

    /**
     * @param int $id
     * @param string|null $track
     * @param int $warehouse_id
     * @param int $courier_id
     * @param int $user_id
     * @param int $address_id
     * @param int $status_id
     * @param string $status
     * @param float $weight
     * @param float $length
     * @param float $width
     * @param float $height
     * @param int $insurance
     * @param string|null $comment
     * @param string|null $date_created
     * @param string|null $date_sent
     * @param ParcelItemDTO[]|null $items
     * @param ServiceDTO[]|null $services
     */
    public function __construct(
        int $id,
        ?string $track,
        int $warehouse_id,
        int $courier_id,
        int $user_id,
        int $address_id,
        int $status_id,
        string $status,
        float $weight,
        float $length,
        float $width,
        float $height,
        int $insurance,
        ?string $comment = null,
        ?string $date_created = null,
        ?string $date_sent = null,
        ?array $items = null,
        ?array $services = null
    ) {
        $this->id = $id;
        $this->track = $track;
        $this->warehouse_id = $warehouse_id;
        $this->courier_id = $courier_id;
        $this->user_id = $user_id;
        $this->address_id = $address_id;
        $this->status_id = $status_id;
        $this->status = $status;
        $this->weight = $weight;
        $this->length = $length;
        $this->width = $width;
        $this->height = $height;
        $this->insurance = $insurance;
        $this->comment = $comment;
        $this->date_created = $date_created;
        $this->date_sent = $date_sent;
        $this->items = $items;
        $this->services = $services;
    }
}
