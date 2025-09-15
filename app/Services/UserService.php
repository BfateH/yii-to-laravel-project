<?php

namespace App\Services;

use App\Models\User;
use App\Events\UserDeleting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserService
{
    public function activate(User $user): void
    {
        $user->update(['is_active' => true]);
    }

    public function deactivate(User $user): void
    {
        $user->update(['is_active' => false]);
    }

    public function ban(User $user, ?string $reason = null): void
    {
        $user->update([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => $reason
        ]);
    }

    public function unban(User $user): void
    {
        $user->update([
            'is_banned' => false,
            'banned_at' => null,
            'ban_reason' => null
        ]);
    }

    public function requestDeletion(User $user): void
    {
        if ($user->id === 1) {
            throw new \InvalidArgumentException('Нельзя удалять пользователя с ID = 1');
        }

        $user->update([
            'delete_requested_at' => now(),
            'delete_confirmation_token' => Str::random(100),
        ]);
    }

    public function forceDelete(User $user): void
    {
        if ($user->id === 1) {
            throw new \InvalidArgumentException('Нельзя удалять пользователя с ID = 1');
        }

        app(UserDeletionService::class)->cleanupUserData($user);
        $user->forceDelete();

        Log::info('Пользователь полностью удален', [
            'user_id' => $user->id,
            'admin_id' => auth()->id()
        ]);
    }

    public function restore(User $user): void
    {
        if (!$user->trashed()) {
            throw new \InvalidArgumentException('Пользователь не был удален');
        }

        $user->restore();
    }
}
