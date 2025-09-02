<?php

namespace App\Modules\OrderManagement\Enums;

enum OrderStatus: int
{
    case NEW = 0;
    case CONFIRMED = 1;
    case PAID = 2;
    case ARRIVING = 4;
    case CANCELED = 5;
    case COMPLETED = 6;
    case SENT = 7;
    case RECEIVED = 8;
    case COMPLETED_PARTIALLY = 9;

    public function toString(): string
    {
        return match($this) {
            self::NEW => 'Создан',
            self::CONFIRMED => 'Подтвержден',
            self::PAID => 'Оплачен',
            self::ARRIVING => 'Ожидается на складе',
            self::CANCELED => 'Отменен',
            self::COMPLETED => 'Выкуплен',
            self::SENT => 'Отправлен (упакован в посылку)',
            self::RECEIVED => 'На складе',
            self::COMPLETED_PARTIALLY => 'Частично выкуплен',
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
