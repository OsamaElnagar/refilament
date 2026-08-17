<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LogicException;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Panel\AuthMode;
use Refilament\Refilament\Refilament;

// Shared auth mode (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md): the panel
// defers entirely to the app's existing auth — it registers no auth routes of
// its own, and the gate redirects unauthenticated visitors to the app's
// `login` route. By construction there is nothing left to collide with an app
// that also enables Fortify.

it('registers no auth routes in shared auth mode', function () {
    app(Refilament::class)->panel()->authMode(AuthMode::Shared);

    app(Refilament::class)->registerAuthRoutes();

    expect(Route::has('refilament.refilament.auth.login'))->toBeFalse();
    expect(Route::has('refilament.refilament.auth.logout'))->toBeFalse();

    $this->get('/refilament/login')->assertNotFound();
    $this->get('/refilament/user/profile')->assertNotFound();
});

it('rejects panel-owned auth pages in shared auth mode', function () {
    $panel = app(Refilament::class)->panel()->authMode(AuthMode::Shared);

    expect(fn () => $panel->login())->toThrow(LogicException::class);
    expect(fn () => $panel->registration())->toThrow(LogicException::class);
    expect(fn () => $panel->passwordReset())->toThrow(LogicException::class);
    expect(fn () => $panel->emailVerification())->toThrow(LogicException::class);
    expect(fn () => $panel->twoFactorAuthentication())->toThrow(LogicException::class);
    expect(fn () => $panel->profile())->toThrow(LogicException::class);
});

it('rejects switching to shared auth mode while auth pages are enabled', function () {
    $panel = app(Refilament::class)->panel()->login();

    expect(fn () => $panel->authMode(AuthMode::Shared))->toThrow(LogicException::class);
});

it('defers the auth gate to the app login in shared auth mode', function () {
    // The testbench runs with fortify.views=false, so the app's own login GET
    // route is not registered — the shared-mode contract assumes the app
    // provides it. Register a stand-in app login to prove the redirect target.
    Route::get('login', fn () => 'app login page')->name('login');

    app(Refilament::class)->panel()
        ->authMode(AuthMode::Shared)
        ->authMiddleware([PanelAuthenticate::class]);

    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');
});

it('carries the intended panel page through the app login flow (guest/intended)', function () {
    Route::get('login', fn () => 'app login page')->name('login');

    // The app's own login: post-login it redirects through intended(), which
    // honors the session Laravel's guest() redirect seeded on the panel side.
    Route::post('login', fn () => redirect()->intended('/'))->name('login.store');

    app(Refilament::class)->panel()
        ->authMode(AuthMode::Shared)
        ->authMiddleware([PanelAuthenticate::class]);

    // Unauthenticated panel page → app login, with the intended panel URL
    // preserved in the session (Redirect::guest stores url.intended)...
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');

    expect(session('url.intended'))->toBe(url('/refilament/posts'));

    // ...and the app's post-login intended() redirect lands the visitor back
    // on the panel page they originally wanted.
    $this->post('/login')
        ->assertRedirect(url('/refilament/posts'));
});
