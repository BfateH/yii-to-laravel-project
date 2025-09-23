<?php

namespace App\Modules\SupportChat\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Открыт',
            self::IN_PROGRESS => 'В работе',
            self::CLOSED => 'Закрыт',
        };
    }

    public function toString(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'success',
            self::IN_PROGRESS => 'warning',
            self::CLOSED => 'secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::OPEN, self::IN_PROGRESS]);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::CLOSED]);
    }
}
