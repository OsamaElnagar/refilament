<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

/**
 * The panel's access gate (slice 1.9 "auth gate").
 *
 * Mounted on the panel's shell-rendering routes (the dashboard, every
 * resource page, and the standalone pages). Whether the gate is actually
 * enforced is decided *per request* from the live panel config
 * (refilament.panel.auth_middleware) — never from a cached Panel instance,
 * because the panel may be built (and its singleton cached) during route
 * registration at boot, before a consumer toggles the gate. Reading config
 * here lets a consumer enable or disable the gate at any time without
 * re-registering routes:
 *
 * - The gate is **enabled** only while its own middleware class appears in the
 *   panel's `auth_middleware` list. When listed, the request must authenticate
 *   against the panel's auth guard (`->authGuard()`) before any shell page
 *   renders; an unauthenticated visitor is redirected to the panel's
 *   `loginUrl`.
 * - Otherwise it passes every request straight through — the permissive
 *   default that keeps the workbench (and the panel itself) open.
 *
 * This mirrors Filament's `authMiddleware()` opt-in and `Authenticate`:
 * enrolling the gate is the switch, and the guard/login target are pure panel
 * config resolved per request, never a cached copy.
 */
class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards): mixed
    {
        // Permissive default: without the gate listed in the panel's auth
        // middleware, the shell pages serve openly (workbench mode).
        if (! in_array(self::class, config('refilament.panel.auth_middleware', []), true)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * The panel's configured guard is the only guard to check — the package
     * exposes a single panel. Mirrors Filament's
     * `Authenticate::authenticate()`, which checks `Filament::auth()`: once an
     * authenticated guard is found, the framework keeps using it; otherwise the
     * request is rejected through `unauthenticated()`, which redirects (or, for
     * the API, throws) and only ever terminates — so nothing runs after it.
     *
     * @param  array<int, string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = $this->auth->guard((string) config('refilament.panel.auth_guard', 'web'));

        if ($guard->check()) {
            $this->auth->shouldUse((string) config('refilament.panel.auth_guard', 'web'));

            return;
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Redirect to the panel's login URL when the visitor isn't authenticated.
     * No login URL configured means the gate is mis-configured (it is only
     * enabled alongside one), so fall back to a 401 the same way Filament's
     * default `redirectTo()` returns null.
     */
    protected function redirectTo($request): ?string
    {
        return config('refilament.panel.login_url');
    }
}
