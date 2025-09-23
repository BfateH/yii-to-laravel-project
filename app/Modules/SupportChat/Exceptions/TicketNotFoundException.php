<?php

namespace App\Modules\SupportChat\Exceptions;

class TicketNotFoundException extends \Exception
{
    protected $message = 'Тикет не найден';

    public function __construct(int $ticketId = null)
    {
        if ($ticketId) {
            $this->message = "Тикет #{$ticketId} не найден";
        }

        parent::__construct($this->message, 404);
    }
}
