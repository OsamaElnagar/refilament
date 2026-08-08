<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Refilament\Refilament\Refilament;

/**
 * The panel's access gate (slice 1.9 "auth gate").
 *
 * Applied to the panel's shell-rendering routes when the app registers it via
 * `Panel::authMiddleware()` (or `refilament.panel.auth_middleware`). Mirrors
 * Filament's `Authenticate`: before any shell page renders, the request must
 * authenticate against the panel's auth guard (`Panel::authGuard()`);
 * otherwise the visitor is redirected to the panel's `loginUrl`. Enforcing a
 * per-user `canAccessPanel()` authorisation contract (Filament's
 * `FilamentUser`) is deferred — the gate here is authentication-only.
 *
 * The check uses the live panel config (the guard and login URL are settled
 * per request), never a cached copy, so changing `refilament.panel.*` takes
 * effect immediately.
 */
class Authenticate extends Middleware
{
    /**
     * The panel's configured guard is the only guard to check — the package
     * exposes a single panel (docs/ARCHITECTURE.md). This mirrors Filament's
     * `Authenticate::authenticate()`, which checks `Filament::auth()`.
     *
     * @param  array<int, string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = $this->auth->guard(app(Refilament::class)->panel()->getAuthGuard());

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(app(Refilament::class)->panel()->getAuthGuard());
    }

    /**
     * Redirect to the panel's login URL when the visitor isn't authenticated.
     * No login URL configured means the gate is mis-configured (the middleware
     * is only ever registered alongside one), so fall back to a 401 the same
     * way Filament's default `redirectTo()` returns null.
     */
    protected function redirectTo($request): ?string
    {
        return app(Refilament::class)->panel()->getLoginUrl();
    }
}
