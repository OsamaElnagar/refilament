<?php

declare(strict_types=1);

use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

// Two-factor authentication stays OFF here (the permissive default) — the
// management page and its endpoints must not exist.
beforeEach(function () {
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->login();

    app(Refilament::class)->registerAuthRoutes();
});

it('does not serve the two-factor settings page when two-factor is disabled', function () {
    $this->actingAs(User::factory()->create())
        ->get('/refilament/user/two-factor')
        ->assertNotFound();
});
