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

it('keeps the panel config defaults permissive on the panel contract', function () {
    $panel = $this->get('/refilament/posts', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    // The auth gate config is server-side only — it never leaks onto the wire.
    expect($panel)->not->toHaveKeys(['auth_middleware', 'auth_guard', 'login_url']);
});
