<?php

namespace App\Services;

use App\Models\User;
use App\Events\UserDeleting;

class UserDeletionService
{
    /**
     * Очистка всех связанных данных пользователя
     */
    public function cleanupUserData(User $user): void
    {
        // Очистка существующих связей
        $user->packages()->delete();
        $user->orders()->delete();
        $user->acquirerConfigs()->delete();

        UserDeleting::dispatch($user);
    }
}
