<?php

namespace App\Modules\SupportChat\Repositories;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Modules\SupportChat\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): TicketMessage
    {
        return TicketMessage::query()->create($data);
    }

    public function findById(int $id): ?TicketMessage
    {
        return TicketMessage::query()->find($id);
    }

    public function findByTicket(Ticket $ticket): Collection
    {
        return $ticket->messages()
            ->with(['user', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function update(TicketMessage $message, array $data): bool
    {
        return $message->update($data);
    }

    public function delete(TicketMessage $message): bool
    {
        return $message->delete();
    }

    public function getLatestMessages(Ticket $ticket, int $limit = 50): Collection
    {
        return $ticket->messages()
            ->with(['user', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function markAsRead(TicketMessage $message): bool
    {
        return true;
    }

    public function getUnreadCount(User $user): int
    {
        return 0;
    }
}
