<?php

use App\Models\User;

test('guests receive unauthorized response', function () {
    $response = $this->get(route('moonshine.index'));
    $response->assertRedirect();
});

test('authenticated users can visit the moonshine.index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('moonshine.index'));
    $response->assertStatus(200);
});
