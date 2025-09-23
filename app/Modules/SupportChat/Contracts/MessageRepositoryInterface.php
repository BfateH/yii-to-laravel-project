<?php

namespace App\Modules\SupportChat\Contracts;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    public function create(array $data): TicketMessage;

    public function findById(int $id): ?TicketMessage;

    public function findByTicket(Ticket $ticket): Collection;

    public function update(TicketMessage $message, array $data): bool;

    public function delete(TicketMessage $message): bool;

    public function getLatestMessages(Ticket $ticket, int $limit = 50): Collection;

    public function markAsRead(TicketMessage $message): bool;

    public function getUnreadCount(User $user): int;
}
