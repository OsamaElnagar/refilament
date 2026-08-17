<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

beforeEach(function () {
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->login()
        ->profile();

    app(Refilament::class)->registerAuthRoutes();
});

it('serves the profile page to an authenticated user', function () {
    $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

    $this->actingAs($user)->get('/refilament/user/profile', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/edit-profile')
        ->assertJsonPath('props.name', 'Test User')
        ->assertJsonPath('props.email', 'test@example.com');
});

it('does not embed 2FA section when two-factor is disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/refilament/user/profile', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.twoFactor');
});

it('embeds 2FA section when two-factor is enabled', function () {
    app(Refilament::class)->panel()->twoFactorAuthentication();
    app(Refilament::class)->registerAuthRoutes();

    $user = User::factory()->create();

    $this->actingAs($user)->get('/refilament/user/profile', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.twoFactor.enabled', false)
        ->assertJsonPath('props.twoFactor.enabling', false);
});

it('redirects an unauthenticated visitor to the panel login', function () {
    $this->get('/refilament/user/profile')
        ->assertRedirect('/refilament/login');
});

it('updates the profile name and email', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $this->actingAs($user);

    $this->put('/refilament/user/profile-information', [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ], ['Accept' => 'application/json'])->assertStatus(200);

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
});

it('rejects an invalid email on profile update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->put('/refilament/user/profile-information', [
        'name' => 'Test',
        'email' => 'not-an-email',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('updates the password', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $this->actingAs($user);

    $this->put('/refilament/user/password', [
        'current_password' => 'secret-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ], ['Accept' => 'application/json'])->assertStatus(200);

    expect(Hash::check('new-strong-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an incorrect current password', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $this->actingAs($user);

    $this->put('/refilament/user/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});
