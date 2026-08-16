<?php

use App\Models\User;

test('guests cannot access the log viewer', function () {
    $this->get('/log-viewer')->assertRedirect(route('login'));
});

test('non-admins cannot access the log viewer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/log-viewer')->assertForbidden();
});

test('admins can access the log viewer', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/log-viewer')->assertOk();
});
