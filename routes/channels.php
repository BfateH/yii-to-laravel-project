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
    $canAccess = false;

    if($ticket && ($user->isAdminRole() || ($ticket->user_id === $user->id))) {
        $canAccess = true;
    }

    if($ticket && $user->isPartnerRole()) {
        $partner = $ticket->user->partner;
        if($partner && $partner->id === $user->id) {
            $canAccess = true;
        }
    }

    return $canAccess;
});

// Канал для админов (для получения уведомлений о новых тикетах)
Broadcast::channel('admin.tickets', function (User $user) {
    return $user->isAdminRole();
});

// Канал для партнеров
Broadcast::channel('partner.{partnerId}.tickets', function (User $user, int $partnerId) {
    return $user->isPartnerRole() && $user->id === $partnerId;
});

// Канал для конкретного пользователя
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
