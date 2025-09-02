<?php

namespace App\Modules\OrderManagement\Enums;

enum PackageStatus: int
{
    case NEW = 0;
    case CANCELED = 1;
    case PACKING = 2;
    case READY = 3;
    case PENDING = 4;
    case PAID = 5;
    case SENT = 6;
    case RECEIVED = 7;
    case TEMPLATE = 8;
    case PRECHECK = 9;

    public function toString(): string
    {
        return match($this) {
            self::NEW => 'Создана',
            self::CANCELED => 'Отменена',
            self::PACKING => 'На упаковке',
            self::READY => 'Готова',
            self::PENDING => 'Ожидает оплаты',
            self::PAID => 'Оплачена',
            self::SENT => 'Отправлена',
            self::RECEIVED => 'Получена',
            self::TEMPLATE => 'Черновик',
            self::PRECHECK => 'Проверка декларации',
        };
    }

    public static function getAll(): array
    {
        $statuses = [];
        foreach (self::cases() as $case) {
            $statuses[$case->value] = $case->toString();
        }

        return $statuses;
    }
}
