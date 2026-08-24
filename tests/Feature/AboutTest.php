<?php

use App\Models\User;

test('guests can view the about page', function () {
    $response = $this->get(route('about'));

    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('About')
        ->where('appName', config('app.name'))
        ->where('phpVersion', PHP_VERSION)
        ->has('laravelVersion'));
});

test('signed in users can view the about page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('about'))->assertOk();
});
