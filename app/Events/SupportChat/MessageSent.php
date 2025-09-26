<?php

namespace App\Events\SupportChat;

use App\Models\TicketMessage;
use App\Models\User;
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
        $channels = [
            new PrivateChannel('ticket.' . $this->message->ticket_id),
            new PrivateChannel('admin.tickets'),
        ];

        $ticketUser = $this->message->user;
        if($ticketUser && $ticketUser->isPartnerRole()) {
            $channels[] = new PrivateChannel("partner.{$ticketUser->id}.tickets");
        }

        if($ticketUser && $ticketUser->isDefaultUserRole()) {
            $partnerUser = $ticketUser->partner;
            if($partnerUser) {
                $channels[] = new PrivateChannel("partner.{$partnerUser->id}.tickets");
            }
        }

        return $channels;
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
