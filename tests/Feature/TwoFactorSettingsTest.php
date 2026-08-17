<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Fortify\RecoveryCode;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

// Arm the two-factor-auth surface + the auth gate, then re-register the auth
// routes (the service provider's booted() hook already ran against the
// permissive-default panel during bootstrap).
beforeEach(function () {
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->login()
        ->twoFactorAuthentication();

    app(Refilament::class)->registerAuthRoutes();
});

// A helper — Fortify's management endpoints are protected by the
// `password.confirm` middleware (the panel enables the confirmPassword
// option), so a test must seed the session's confirmation timestamp before
// hitting them.
function confirmPassword(): void
{
    session(['auth.password_confirmed_at' => time()]);
}

function confirmedTwoFactorUser(): User
{
    $user = User::factory()->create(['email' => '2fa@example.com', 'password' => 'secret-password']);

    $user->forceFill([
        'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

it('serves the two-factor settings page to an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/refilament/user/two-factor', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/two-factor-settings')
        ->assertJsonPath('props.enabled', false)
        ->assertJsonPath('props.enabling', false);
});

it('redirects an unauthenticated visitor to the panel login', function () {
    $this->get('/refilament/user/two-factor')
        ->assertRedirect('/refilament/login');
});

it('enables two-factor after a password confirmation and confirms the code', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $this->actingAs($user);

    // 1. The management endpoints are gated behind password confirmation.
    $this->post('/refilament/user/two-factor-authentication', [], [
        'Accept' => 'application/json',
    ])->assertStatus(423);

    // 2. Confirm the password through Fortify's endpoint (JSON → 201).
    $this->post('/refilament/user/confirm-password', ['password' => 'secret-password'], [
        'Accept' => 'application/json',
    ])->assertStatus(201);

    // 3. Enable — the secret + recovery codes are generated.
    $this->post('/refilament/user/two-factor-authentication', [], [
        'Accept' => 'application/json',
    ])->assertStatus(200);

    expect($user->fresh()->two_factor_secret)->not->toBeNull();

    // 4. The page now reports the mid-setup "enabling" state.
    $this->get('/refilament/user/two-factor', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.enabling', true)
        ->assertJsonPath('props.enabled', false);

    // 5. QR code + secret key endpoints return the setup data.
    $this->get('/refilament/user/two-factor-qr-code', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['svg', 'url']);

    $this->get('/refilament/user/two-factor-secret-key', ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['secretKey']);

    // 6. Confirm the code generated from the user's secret.
    $secret = decrypt($user->fresh()->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post('/refilament/user/confirmed-two-factor-authentication', ['code' => $code], [
        'Accept' => 'application/json',
    ])->assertStatus(200);

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();

    // 7. The page now reports "enabled".
    $this->get('/refilament/user/two-factor', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.enabled', true)
        ->assertJsonPath('props.enabling', false);
});

it('rejects an invalid confirmation code', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $this->actingAs($user);
    confirmPassword();

    $this->post('/refilament/user/two-factor-authentication', [], ['Accept' => 'application/json'])
        ->assertStatus(200);

    $this->post('/refilament/user/confirmed-two-factor-authentication', ['code' => '000000'], [
        'Accept' => 'application/json',
    ])->assertStatus(422);

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('returns recovery codes and regenerates them', function () {
    $user = confirmedTwoFactorUser();
    $this->actingAs($user);
    confirmPassword();

    $before = $this->get('/refilament/user/two-factor-recovery-codes', ['Accept' => 'application/json'])
        ->assertOk()
        ->json();

    expect($before)->toBeArray()->not->toBeEmpty();

    $this->post('/refilament/user/two-factor-recovery-codes', [], ['Accept' => 'application/json'])
        ->assertStatus(200);

    $after = $this->get('/refilament/user/two-factor-recovery-codes', ['Accept' => 'application/json'])
        ->assertOk()
        ->json();

    expect($after)->toBeArray()->not->toBeEmpty();
    expect($after)->not->toEqual($before);
});

it('disables two-factor after a password confirmation', function () {
    $user = confirmedTwoFactorUser();
    $this->actingAs($user);

    // Without a fresh confirmation the disable is blocked.
    $this->delete('/refilament/user/two-factor-authentication', [], ['Accept' => 'application/json'])
        ->assertStatus(423);

    $this->post('/refilament/user/confirm-password', ['password' => 'secret-password'], [
        'Accept' => 'application/json',
    ])->assertStatus(201);

    $this->delete('/refilament/user/two-factor-authentication', [], ['Accept' => 'application/json'])
        ->assertStatus(200);

    expect($user->fresh()->two_factor_secret)->toBeNull();

    $this->get('/refilament/user/two-factor', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.enabled', false)
        ->assertJsonPath('props.enabling', false);
});
