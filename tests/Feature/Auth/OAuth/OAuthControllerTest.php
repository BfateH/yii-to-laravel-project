<?php

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it redirects to provider', function () {
    $response = $this->get('/auth/google/redirect');
    $response->assertRedirect();
});

test('it handles provider callback and creates user', function () {
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

test('it handles callback without email', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => '123',
        'email' => null,
        'name' => 'Test User',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $response->assertSessionHasErrors('oauth');

    // Проверяем что ошибка содержит нужный текст
    $errorMessage = session('errors')->first('oauth');
    $this->assertStringContainsString('Не удалось получить email от провайдера', $errorMessage);
});

test('it handles authentication error', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new \Exception('Authentication failed'));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $response->assertSessionHasErrors('oauth');

    // Проверяем что ошибка содержит нужный текст
    $errorMessage = session('errors')->first('oauth');
    $this->assertStringContainsString('Ошибка аутентификации через Google', $errorMessage);
});
