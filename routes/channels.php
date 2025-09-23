<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


// Канал для конкретного тикета
Broadcast::channel('ticket.{ticketId}', function (User $user, int $ticketId) {
    $ticket = Ticket::find($ticketId);
    return $ticket && ($ticket->user_id === $user->id || $user->isAdminRole());
});

// Канал для админов (для получения уведомлений о новых тикетах)
Broadcast::channel('admin.tickets', function (User $user) {
    return $user->isAdminRole();
});

// Канал для конкретного пользователя
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
