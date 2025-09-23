<?php

namespace App\Modules\SupportChat\Enums;

enum TicketCategory: string
{
    case ORDER = 'order';
    case GENERAL = 'general';
    case PAYMENT = 'payment';
    case TECHNICAL = 'technical';

    public function label(): string
    {
        return match($this) {
            self::ORDER => 'По заказу',
            self::GENERAL => 'Общий вопрос',
            self::PAYMENT => 'Оплата',
            self::TECHNICAL => 'Техническая поддержка',
        };
    }

    public function toString(): string
    {
        return $this->label();
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}
