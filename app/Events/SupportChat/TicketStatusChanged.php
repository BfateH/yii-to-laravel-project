<?php

namespace App\Events\SupportChat;

use App\Models\Ticket;
use App\Modules\SupportChat\Enums\TicketStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketStatus $oldStatus
    ) {
        $this->ticket->load(['user']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ticket.' . $this->ticket->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'status' => $this->ticket->status,
                'old_status' => $this->oldStatus->value,
                'closed_at' => $this->ticket->closed_at?->toISOString(),
                'updated_at' => $this->ticket->updated_at->toISOString(),
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.status.changed';
    }
}
