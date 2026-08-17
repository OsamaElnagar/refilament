<?php

declare(strict_types=1);

use Laravel\Fortify\Contracts\LogoutResponse;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

it('shares no user for guests', function () {
    $props = $this->get('/refilament', ['X-Inertia' => 'true'])->json('props.refilament');

    expect($props)->not->toHaveKey('user');
});

it('shares the authenticated user name and email with the shell', function () {
    $user = User::factory()->create(['name' => 'Osama Elrefaei', 'email' => 'osama@example.com']);

    $this->actingAs($user)
        ->get('/refilament', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.refilament.user.name', 'Osama Elrefaei')
        ->assertJsonPath('props.refilament.user.email', 'osama@example.com');
});

it('shares the profile and two-factor urls only when their features are enabled', function () {
    // Defaults off — neither URL leaks onto the wire.
    $props = $this->get('/refilament', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    expect($props)->not->toHaveKeys(['profileUrl', 'twoFactorUrl', 'logoutUrl']);

    // Arming the features shares their URLs.
    app(Refilament::class)->panel()
        ->profile()
        ->twoFactorAuthentication();

    $props = $this->get('/refilament', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    expect($props['profileUrl'])->toBe('/refilament/user/profile');
    expect($props['twoFactorUrl'])->toBe('/refilament/user/two-factor');
});

it('shares the logout url only when an auth page is mounted', function () {
    app(Refilament::class)->panel()->login();

    $props = $this->get('/refilament', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    expect($props['logoutUrl'])->toBe('/refilament/logout');
});

it('logs the user out through the panel logout route and returns to the login page', function () {
    $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret-password']);

    // Arm the panel's auth surface like a consumer, then re-register the auth
    // routes (the service provider's booted() hook already ran with the
    // permissive default) — this also re-binds the panel LogoutResponse.
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->login();

    app(Refilament::class)->registerAuthRoutes();

    $this->actingAs($user)
        ->post('/refilament/logout')
        ->assertRedirect('/refilament/login');

    $this->assertGuest();
});

it('returns 204 for a JSON logout', function () {
    $user = User::factory()->create();

    app(Refilament::class)->panel()->login();

    app(Refilament::class)->registerAuthRoutes();

    $this->actingAs($user)
        ->postJson('/refilament/logout')
        ->assertNoContent();

    $this->assertGuest();
});

it('lets a consumer override the logout response', function () {
    $user = User::factory()->create();

    app(Refilament::class)->panel()->login();

    // Bind our own response before the routes register, mirroring a consumer's
    // LogoutResponse binding in a service provider — the package's binding
    // (app()->bound check) then leaves it alone.
    app()->instance(
        LogoutResponse::class,
        new class implements LogoutResponse
        {
            public function toResponse($request)
            {
                return redirect('/somewhere-else');
            }
        },
    );

    app(Refilament::class)->registerAuthRoutes();

    $this->actingAs($user)
        ->post('/refilament/logout')
        ->assertRedirect('/somewhere-else');

    $this->assertGuest();
});
