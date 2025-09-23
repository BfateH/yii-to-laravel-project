<?php

namespace App\Events\SupportChat;

use App\Models\TicketMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TicketMessage $message
    ) {
        $this->message->load(['user', 'attachments']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ticket.' . $this->message->ticket_id),
            new PrivateChannel('admin.tickets'),
        ];
    }

    public function broadcastWith(): array
    {
        $data = [
            'message' => [
                'id' => $this->message->id,
                'ticket_id' => $this->message->ticket_id,
                'user' => [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                ],
                'message' => $this->message->message,
                'is_admin' => $this->message->is_admin,
                'attachments' => $this->message->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'url' => $attachment->url,
                        'original_name' => $attachment->original_name,
                        'is_image' => $attachment->isImage(),
                    ];
                }),
                'created_at' => $this->message->created_at->toISOString(),
            ]
        ];

        return $data;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
