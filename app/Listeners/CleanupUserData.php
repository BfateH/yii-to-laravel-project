<?php

namespace App\Listeners;

use App\Events\UserDeleting;

class CleanupUserData
{
    public function handle(UserDeleting $event): void
    {
        $user = $event->user;

        // Здесь можно добавить дополнительную логику очистки
        // которая будет вызываться при удалении пользователя
    }
}
