<?php

namespace Tests\Unit\Events;

use App\Enums\Role;
use App\Events\UserDeleting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeletingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_be_instantiated_with_user()
    {
        $user = User::factory()->create();

        $event = new UserDeleting($user);

        $this->assertSame($user, $event->user);
    }

    public function test_it_uses_serializes_models()
    {
        $user = User::factory()->create();

        $event = new UserDeleting($user);

        // Проверяем, что событие может быть сериализовано
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(UserDeleting::class, $unserialized);
        $this->assertEquals($user->id, $unserialized->user->id);
    }

    public function test_event_contains_correct_user_data()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role_id' => Role::user->value
        ]);

        $event = new UserDeleting($user);

        $this->assertEquals('Test User', $event->user->name);
        $this->assertEquals('test@example.com', $event->user->email);
        $this->assertEquals(Role::user->value, $event->user->role_id);
    }
}
