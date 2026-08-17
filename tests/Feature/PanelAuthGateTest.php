<?php

declare(strict_types=1);

use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

it('serves the shell pages openly by default', function () {
    // The panel auth gate is off unless the live panel's authMiddleware()
    // enlists it — the permissive default that keeps the workbench (and a
    // fresh install) open.
    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/dashboard');

    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-table');
});

it('redirects unauthenticated visitors to the login url when the gate is enabled', function () {
    // The gate reads the *live* panel per request, so arming it on the
    // resolved panel instance is enough — no config, no re-registration.
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->loginUrl('/login');

    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');

    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');
});

it('lets an authenticated user through the gate', function () {
    $user = User::factory()->create();

    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->loginUrl('/login');

    $this->actingAs($user)
        ->get('/refilament', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/dashboard');
});

it('gates the typed endpoints too when the gate is enabled', function () {
    // The whole panel sits behind authMiddleware() (mirroring Filament): the
    // typed endpoints are as gated as the shell pages, so an unauthenticated
    // JSON request to the table endpoint gets 401, not data.
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->loginUrl('/login');

    $this->getJson('/refilament/table/posts')
        ->assertUnauthorized();

    // A browser-style fetch (no Accept: application/json) is redirected to
    // the login url like any other unauthenticated shell request.
    $this->get('/refilament/table/posts', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');
});

it('serves the typed endpoints to an authenticated user when the gate is enabled', function () {
    $user = User::factory()->create();

    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->loginUrl('/login');

    $this->actingAs($user)
        ->getJson('/refilament/table/posts')
        ->assertOk()
        ->assertJsonStructure(['rows', 'total']);
});

it('keeps the panel config defaults permissive on the panel contract', function () {
    $panel = $this->get('/refilament/posts', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    // The auth gate config is server-side only — it never leaks onto the wire.
    expect($panel)->not->toHaveKeys(['auth_middleware', 'auth_guard', 'login_url']);
});
