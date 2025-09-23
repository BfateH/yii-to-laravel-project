<?php

namespace App\Events\SupportChat;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket
    ) {
        $this->ticket->load(['user']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.tickets'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'subject' => $this->ticket->subject,
                'category' => $this->ticket->category,
                'status' => $this->ticket->status,
                'user' => [
                    'id' => $this->ticket->user->id,
                    'name' => $this->ticket->user->name,
                ],
                'created_at' => $this->ticket->created_at->toISOString(),
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }
}
