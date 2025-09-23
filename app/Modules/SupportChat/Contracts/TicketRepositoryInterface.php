<?php

namespace App\Modules\SupportChat\Contracts;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\SupportChat\Enums\TicketCategory;
use App\Modules\SupportChat\Enums\TicketStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TicketRepositoryInterface
{
    public function create(array $data): Ticket;

    public function findById(int $id): ?Ticket;

    public function findByUser(User $user, array $filters = []): LengthAwarePaginator;

    public function findAll(array $filters = []): LengthAwarePaginator;

    public function update(Ticket $ticket, array $data): bool;

    public function delete(Ticket $ticket): bool;

    public function changeStatus(Ticket $ticket, TicketStatus $status): bool;

    public function getByCategory(TicketCategory $category): Collection;

    public function getOpenTickets(): Collection;

    public function getUserTickets(User $user): Collection;

    public function search(string $query, array $filters = []): LengthAwarePaginator;
}
