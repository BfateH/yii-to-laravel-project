<?php

namespace Tests\Feature\Http;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountDeletionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем пользователя без указания ID (автоинкремент)
        $this->user = User::factory()->create([
            'role_id' => Role::user->value,
            'is_active' => true
        ]);

        // Убедимся, что ID не равен 1
        if ($this->user->id === 1) {
            // Если получили ID=1, создаем еще одного пользователя
            $this->user = User::factory()->create([
                'role_id' => Role::user->value,
                'is_active' => true
            ]);
        }
    }

    public function test_user_can_request_account_deletion()
    {
        Notification::fake();

        // Проверяем, что у пользователя ID не равен 1
        $this->assertNotEquals(1, $this->user->id, 'User ID should not be 1');

        $response = $this->actingAs($this->user, 'moonshine')
            ->post('/admin/method/profile-page', [
                '_token' => csrf_token(),
                'method' => 'sendDeleteRequest',
                '_component_name' => 'default'
            ]);

        $response->assertStatus(200);
        $this->user->refresh();

        $this->assertNotNull($this->user->delete_requested_at);
        $this->assertNotNull($this->user->delete_confirmation_token);
        Notification::assertSentTo($this->user, AccountDeletionRequested::class);
    }

    public function test_user_can_confirm_account_deletion()
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = 'test-token-1234567890';
        $user->update([
            'delete_requested_at' => now(),
            'delete_confirmation_token' => $token
        ]);

        $response = $this->get('/confirm-delete/' . $token);

        $response->assertRedirect('/admin/login');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_expired_deletion_token_fails()
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = 'expired-token-1234567890';
        $user->update([
            'delete_requested_at' => now()->subHours(25), // больше 24 часов
            'delete_confirmation_token' => $token
        ]);

        $response = $this->get('/confirm-delete/' . $token);

        $response->assertRedirect('/admin/login');

        // Пользователь должен остаться
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_invalid_deletion_token_fails()
    {
        $user = User::factory()->create(['is_active' => true]);
        $validToken = 'valid-token-1234567890';
        $invalidToken = 'invalid-token-0987654321';
        $user->update([
            'delete_requested_at' => now(),
            'delete_confirmation_token' => $validToken
        ]);

        $response = $this->get('/confirm-delete/' . $invalidToken);

        $response->assertRedirect('/admin/login');

        // Пользователь должен остаться
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_can_update_profile()
    {
        $response = $this->actingAs($this->user, 'moonshine')
            ->post(route('moonshine.admin.profile.store'), [
                '_token' => csrf_token(),
                'name' => 'Updated Name',
                'email' => $this->user->email,
            ]);

        $response->assertStatus(302);
        $this->user->refresh();
        $this->assertEquals('Updated Name', $this->user->name);
    }
}
