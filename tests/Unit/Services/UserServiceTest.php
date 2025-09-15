<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
    }

    public function test_it_can_activate_user()
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->userService->activate($user);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_it_can_deactivate_user()
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->userService->deactivate($user);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_it_can_ban_user()
    {
        $user = User::factory()->create(['is_banned' => false]);
        $reason = 'Test reason';
        $this->userService->ban($user, $reason);
        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->is_banned);
        $this->assertEquals($reason, $freshUser->ban_reason);
        $this->assertNotNull($freshUser->banned_at);
    }

    public function test_it_can_unban_user()
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => 'Test reason'
        ]);

        $this->userService->unban($user);

        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->is_banned);
        $this->assertNull($freshUser->banned_at);
        $this->assertNull($freshUser->ban_reason);
    }

    public function test_it_throws_exception_when_deleting_user_id_1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Нельзя удалять пользователя с ID = 1');

        $user = User::factory()->create(['id' => 1]);
        $this->userService->forceDelete($user);
    }

    public function test_it_can_request_user_deletion()
    {
        $user = User::factory()->create(['id' => 2]);

        $this->userService->requestDeletion($user);

        $freshUser = $user->fresh();
        $this->assertNotNull($freshUser->delete_requested_at);
        $this->assertNotNull($freshUser->delete_confirmation_token);
        $this->assertEquals(100, strlen($freshUser->delete_confirmation_token));
    }

    public function test_it_can_restore_user()
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertTrue($user->fresh()->trashed());

        $this->userService->restore($user);

        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_it_throws_exception_when_restoring_non_deleted_user()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Пользователь не был удален');

        $user = User::factory()->create();

        $this->userService->restore($user);
    }
}
