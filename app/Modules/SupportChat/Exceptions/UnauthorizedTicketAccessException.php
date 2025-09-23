<?php

namespace App\Modules\SupportChat\Exceptions;

class UnauthorizedTicketAccessException extends \Exception
{
    protected $message = 'У вас нет доступа к этому тикету';

    public function __construct(int $ticketId = null)
    {
        if ($ticketId) {
            $this->message = "У вас нет доступа к тикету #{$ticketId}";
        }

        parent::__construct($this->message, 403);
    }
}
