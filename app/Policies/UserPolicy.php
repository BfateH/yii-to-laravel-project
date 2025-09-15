<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authenticatedUser): bool
    {
        return $authenticatedUser->isAdminRole() || $authenticatedUser->isPartnerRole();
    }

    public function view(User $authenticatedUser, User $targetUser): bool
    {
        if ($authenticatedUser->isAdminRole()) {
            return true;
        }

        if ($authenticatedUser->isPartnerRole()) {
            return $targetUser->partner_id === $authenticatedUser->id;
        }

        return false;
    }

    public function create(User $authenticatedUser): bool
    {
        return $authenticatedUser->isAdminRole() || $authenticatedUser->isPartnerRole();
    }

    public function update(User $authenticatedUser, User $targetUser): bool
    {
        if ($targetUser->isPartnerRole()) {
            return false;
        }

        if ($authenticatedUser->isAdminRole()) {
            return true;
        }

        if ($authenticatedUser->isPartnerRole()) {
            return $targetUser->partner_id === $authenticatedUser->id;
        }

        return false;
    }

    public function updatePartner(User $authenticatedUser, User $targetUser): bool
    {
        if ($authenticatedUser->isAdminRole()) {
            return true;
        }

        if ($targetUser->isPartnerRole()) {
            return true;
        }

        return false;
    }

    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        // Нельзя удалять админа (ID = 1)
        if ($targetUser->id === 1) {
            return false;
        }

        // Партнеров нельзя удалять через этот ресурс
        if ($targetUser->isPartnerRole()) {
            return false;
        }

        if ($authenticatedUser->isAdminRole()) {
            return true;
        }

        if ($authenticatedUser->isPartnerRole()) {
            return $targetUser->partner_id === $authenticatedUser->id;
        }

        return false;
    }

    public function restore(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->isAdminRole() || $authenticatedUser->isPartnerRole();
    }

    public function forceDelete(User $authenticatedUser, User $targetUser): bool
    {
        if ($targetUser->id === 1) {
            return false;
        }

        if ($targetUser->isPartnerRole()) {
            return false;
        }

        if ($authenticatedUser->isAdminRole()) {
            return true;
        }

        if ($authenticatedUser->isPartnerRole()) {
            return $targetUser->partner_id === $authenticatedUser->id;
        }

        return false;
    }
}
