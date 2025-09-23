<?php

namespace App\Modules\SupportChat\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Modules\SupportChat\Contracts\WebSocketServiceInterface;
use App\Modules\SupportChat\Enums\TicketStatus;

class WebSocketService implements WebSocketServiceInterface
{
    public function broadcastMessage(TicketMessage $message): void
    {
        broadcast(new \App\Events\SupportChat\MessageSent($message))->toOthers();
    }

    public function broadcastTicketCreated(Ticket $ticket): void
    {
        broadcast(new \App\Events\SupportChat\TicketCreated($ticket));
    }

    public function broadcastTicketStatusChanged(Ticket $ticket, TicketStatus $oldStatus): void
    {
        broadcast(new \App\Events\SupportChat\TicketStatusChanged($ticket, $oldStatus));
    }

    public function subscribeToTicketChannel(int $ticketId, int $userId): void
    {
        //
    }

    public function unsubscribeFromTicketChannel(int $ticketId, int $userId): void
    {
        //
    }
}
