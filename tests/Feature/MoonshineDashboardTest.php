<?php

use App\Enums\Role;
use App\Models\User;

test('guests receive unauthorized response', function () {
    $response = $this->get(route('moonshine.index'));
    $response->assertRedirect();
});

test('authenticated users can visit the moonshine.index', function () {
    $user = User::factory()->create([
        'is_active' => true,
        'is_banned' => false,
        'role_id' => Role::user->value
    ]);

    $response = $this->actingAs($user, 'moonshine')
        ->get(route('moonshine.index'));

    $response->assertStatus(200);
});
