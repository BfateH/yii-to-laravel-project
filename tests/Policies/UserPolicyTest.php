<?php

namespace Tests\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy();
    }

    public function test_admin_can_view_any_user()
    {
        $admin = User::factory()->create(['role_id' => Role::admin->value]);
        $user = User::factory()->create(['role_id' => Role::user->value]);

        $this->assertTrue($this->policy->viewAny($admin));
    }

    public function test_partner_can_view_any_user()
    {
        $partner = User::factory()->create(['role_id' => Role::partner->value]);

        $this->assertTrue($this->policy->viewAny($partner));
    }

    public function test_regular_user_cannot_view_any_user()
    {
        $user = User::factory()->create(['role_id' => Role::user->value]);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_admin_can_update_user()
    {
        $admin = User::factory()->create(['role_id' => Role::admin->value]);
        $user = User::factory()->create(['role_id' => Role::user->value]);

        $this->assertTrue($this->policy->update($admin, $user));
    }

    public function test_partner_can_update_own_users()
    {
        $partner = User::factory()->create(['role_id' => Role::partner->value]);
        $user = User::factory()->create([
            'role_id' => Role::user->value,
            'partner_id' => $partner->id
        ]);

        $this->assertTrue($this->policy->update($partner, $user));
    }

    public function test_partner_cannot_update_other_partner_users()
    {
        $partner1 = User::factory()->create(['role_id' => Role::partner->value]);
        $partner2 = User::factory()->create(['role_id' => Role::partner->value]);
        $user = User::factory()->create([
            'role_id' => Role::user->value,
            'partner_id' => $partner2->id
        ]);

        $this->assertFalse($this->policy->update($partner1, $user));
    }

    public function test_admin_cannot_delete_user_id_1()
    {
        $admin = User::factory()->create(['role_id' => Role::admin->value]);
        $user = User::factory()->create(['id' => 1]); // Admin user

        $this->assertFalse($this->policy->delete($admin, $user));
    }

    public function test_admin_cannot_force_delete_user_id_1()
    {
        $admin = User::factory()->create(['role_id' => Role::admin->value]);
        $user = User::factory()->create(['id' => 1]); // Admin user

        $this->assertFalse($this->policy->forceDelete($admin, $user));
    }

    public function test_admin_can_delete_regular_user()
    {
        $admin = User::factory()->create(['role_id' => Role::admin->value]);
        $user = User::factory()->create(['id' => 2, 'role_id' => Role::user->value]);

        $this->assertTrue($this->policy->delete($admin, $user));
        $this->assertTrue($this->policy->forceDelete($admin, $user));
    }

    public function test_partner_can_delete_assigned_user()
    {
        $partner = User::factory()->create(['role_id' => Role::partner->value]);
        $user = User::factory()->create([
            'id' => 2,
            'role_id' => Role::user->value,
            'partner_id' => $partner->id
        ]);

        $this->assertTrue($this->policy->delete($partner, $user));
        $this->assertTrue($this->policy->forceDelete($partner, $user));
    }

    public function test_partner_cannot_delete_unassigned_user()
    {
        $partner = User::factory()->create(['role_id' => Role::partner->value]);
        $user = User::factory()->create([
            'id' => 2,
            'role_id' => Role::user->value,
            'partner_id' => 999 // Другой партнер
        ]);

        $this->assertFalse($this->policy->delete($partner, $user));
        $this->assertFalse($this->policy->forceDelete($partner, $user));
    }
}
