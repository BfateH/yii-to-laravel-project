<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it completes full oauth flow', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => '123',
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'google_id' => '123'
    ]);

    $this->assertAuthenticated();
});

test('it handles oauth flow for existing user', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'google_id' => null
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => '123',
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');
    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'google_id' => '123'
    ]);
});
