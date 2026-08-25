<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Isolates profile-photo tests from the real storage disk - User::removeOrphanProfileImages()
    // deletes any file not referenced by a user row in the (rolled-back) test database,
    // so running it against the real disk would wipe real uploads.
    Storage::fake('public');
});

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});

test('a profile photo can be uploaded', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    $filename = $user->refresh()->profile_image;

    expect($filename)->not->toBeNull();
    Storage::disk('public')->assertExists(User::profileStoragePath($filename));
});

test('uploading a new profile photo replaces the old one', function () {
    Storage::disk('public')->put(User::profileStoragePath('old.jpg'), 'fake image contents');
    $user = User::factory()->create(['profile_image' => 'old.jpg']);

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertSessionHasNoErrors();

    $filename = $user->refresh()->profile_image;

    expect($filename)->not->toBe('old.jpg');
    Storage::disk('public')->assertMissing(User::profileStoragePath('old.jpg'));
    Storage::disk('public')->assertExists(User::profileStoragePath($filename));
});

test('a profile photo can be removed', function () {
    Storage::disk('public')->put(User::profileStoragePath('old.jpg'), 'fake image contents');
    $user = User::factory()->create(['profile_image' => 'old.jpg']);

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'remove_profile_image' => '1',
    ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->profile_image)->toBeNull();
    Storage::disk('public')->assertMissing(User::profileStoragePath('old.jpg'));
});

test('the profile photo must be an image', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'profile_image' => UploadedFile::fake()->create('document.pdf', 100),
    ]);

    $response->assertSessionHasErrors('profile_image');
    expect($user->refresh()->profile_image)->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('the orphan sweep leaves the directory .gitignore alone', function () {
    // storage/app/public/profile/.gitignore is a tracked file and is what keeps
    // the directory in the repository. No user row points at it, so an
    // unfiltered sweep deletes it on the first real profile update.
    Storage::disk('public')->put(User::profileStoragePath('.gitignore'), "*\n!.gitignore\n");
    Storage::disk('public')->put(User::profileStoragePath('orphan.jpg'), 'nobody points here');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists(User::profileStoragePath('.gitignore'));
    Storage::disk('public')->assertMissing(User::profileStoragePath('orphan.jpg'));
});
