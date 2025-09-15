<?php

namespace Tests\Fixtures;

use App\Enums\Role;
use App\Models\User;

class Users
{
    public static function admin(): User
    {
        return User::factory()->create([
            'role_id' => Role::admin->value,
            'email' => 'admin@example.com',
            'name' => 'Admin User'
        ]);
    }

    public static function regularUser(): User
    {
        return User::factory()->create([
            'role_id' => Role::user->value,
            'email' => 'user@example.com',
            'name' => 'Regular User'
        ]);
    }

    public static function partner(): User
    {
        return User::factory()->create([
            'role_id' => Role::partner->value,
            'email' => 'partner@example.com',
            'name' => 'Partner User'
        ]);
    }

    public static function userWithPartner(User $partner): User
    {
        return User::factory()->create([
            'role_id' => Role::user->value,
            'partner_id' => $partner->id,
            'email' => 'assigneduser@example.com',
            'name' => 'Assigned User'
        ]);
    }
}
