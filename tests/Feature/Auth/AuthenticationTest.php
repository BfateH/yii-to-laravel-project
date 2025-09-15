<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get(route('moonshine.login'));

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/admin/authenticate', [
        'username' => $user->email,
        'password' => 'password',
    ]);


    // Проверка редиректа
    $response->assertRedirect(route('moonshine.index'));

    // Проверка аутентификации
    $this->assertAuthenticatedAs($user, 'moonshine');
});


test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/admin/authenticate', [
        'username' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('moonshine.logout'));

    $this->assertGuest();
    $response->assertRedirect(route('moonshine.login'));
});
