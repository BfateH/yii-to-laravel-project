<?php

namespace Tests\Unit\Services;

use App\Events\UserDeleting;
use App\Models\User;
use App\Services\UserDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserDeletionService $deletionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deletionService = new UserDeletionService();
    }

    public function test_it_dispatches_event_when_cleaning_user_data()
    {
        Event::fake();

        $user = User::factory()->create();

        $this->deletionService->cleanupUserData($user);

        Event::assertDispatched(UserDeleting::class);
    }

    public function test_it_handles_user_without_related_data()
    {
        $user = User::factory()->create();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->deletionService->cleanupUserData($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_it_calls_user_deleting_event()
    {
        Event::fake();

        $user = User::factory()->create();

        $this->deletionService->cleanupUserData($user);

        Event::assertDispatched(UserDeleting::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }
}
