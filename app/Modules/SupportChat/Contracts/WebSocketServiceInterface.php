<?php

namespace App\Modules\SupportChat\Contracts;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Modules\SupportChat\Enums\TicketStatus;

interface WebSocketServiceInterface
{
    public function broadcastMessage(TicketMessage $message): void;

    public function broadcastTicketCreated(Ticket $ticket): void;

    public function broadcastTicketStatusChanged(Ticket $ticket, TicketStatus $oldStatus): void;

    public function subscribeToTicketChannel(int $ticketId, int $userId): void;

    public function unsubscribeFromTicketChannel(int $ticketId, int $userId): void;
}
