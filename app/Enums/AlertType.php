<?php

namespace App\Enums;

enum AlertType: string
{
    case TICKET_MESSAGE_CREATED = 'ticket_message_created';
    case TICKET_CREATED = 'ticket_created';

    public function label(): string
    {
        return match($this) {
            self::TICKET_MESSAGE_CREATED => 'Сообщение в тикете создано',
            self::TICKET_CREATED => 'Тикет создан',
        };
    }

    public function toString(): string
    {
        return $this->label();
    }
}
