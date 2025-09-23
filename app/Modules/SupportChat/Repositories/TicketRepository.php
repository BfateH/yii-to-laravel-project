<?php

namespace App\Modules\SupportChat\Repositories;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\SupportChat\Contracts\TicketRepositoryInterface;
use App\Modules\SupportChat\Enums\TicketCategory;
use App\Modules\SupportChat\Enums\TicketStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository implements TicketRepositoryInterface
{
    public function create(array $data): Ticket
    {
        return Ticket::query()->create($data);
    }

    public function findById(int $id): ?Ticket
    {
        return Ticket::query()->find($id);
    }

    public function findByUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->tickets()->with(['user', 'messages']);
        return $this->applyFilters($query, $filters)->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    public function findAll(array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::with(['user', 'messages']);

        return $this->applyFilters($query, $filters)
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function update(Ticket $ticket, array $data): bool
    {
        return $ticket->update($data);
    }

    public function delete(Ticket $ticket): bool
    {
        return $ticket->delete();
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status): bool
    {
        $data = ['status' => $status->value];

        if ($status->isClosed() && !$ticket->closed_at) {
            $data['closed_at'] = now();
        } elseif ($status->isOpen() && $ticket->closed_at) {
            $data['closed_at'] = null;
        }

        return $ticket->update($data);
    }

    public function getByCategory(TicketCategory $category): Collection
    {
        return Ticket::query()->where('category', $category->value)->get();
    }

    public function getOpenTickets(): Collection
    {
        return Ticket::open()->get();
    }

    public function getUserTickets(User $user): Collection
    {
        return $user->tickets()->get();
    }

    public function search(string $query, array $filters = []): LengthAwarePaginator
    {
        $searchQuery = Ticket::with(['user', 'messages'])
            ->where(function ($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });

        return $this->applyFilters($searchQuery, $filters)
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    private function applyFilters($query, array $filters)
    {
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
