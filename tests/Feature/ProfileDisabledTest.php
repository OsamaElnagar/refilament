<?php

declare(strict_types=1);

use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

// Profile page stays OFF (the permissive default) — the route must not exist.
beforeEach(function () {
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->login();

    app(Refilament::class)->registerAuthRoutes();
});

it('does not serve the profile page when profile is disabled', function () {
    $this->actingAs(User::factory()->create())
        ->get('/refilament/user/profile')
        ->assertNotFound();
});
