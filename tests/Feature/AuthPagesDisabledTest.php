<?php

declare(strict_types=1);
use Refilament\Refilament\Http\Middleware\Authenticate;

it('registers no auth routes by default', function () {
    // The panel ships with no first-party auth pages (matching Filament) —
    // the permissive workbench keeps working, and Fortify stays out of the
    // way (its own view routes are suppressed when no config is published).
    expect(Route::has('login'))->toBeFalse();
    expect(Route::has('refilament.login'))->toBeFalse();

    $this->get('/refilament/login')->assertNotFound();
    $this->get('/refilament/register')->assertNotFound();
});

it('keeps the auth gate redirect target when no login page is enabled', function () {
    // The previous turn's behavior: an explicit ->loginUrl() is honored even
    // without a first-party login page.
    app(Refilament\Refilament\Refilament::class)->panel()
        ->authMiddleware([Authenticate::class])
        ->loginUrl('/login');

    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');
});
