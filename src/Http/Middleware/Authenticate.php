<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Refilament\Refilament\Refilament;

/**
 * The panel's access gate (slice 1.9 "auth gate").
 *
 * Mounted on **every** panel route — the shell pages (the dashboard, every
 * resource page, the standalone pages) and the typed endpoints (table,
 * schema, action, notifications) alike, mirroring Filament, where the whole
 * panel sits behind `authMiddleware()`. Whether the gate is actually
 * enforced is decided *per request* from the live panel
 * (Refilament::panel()->getAuthMiddleware()) — never from a cached Panel
 * instance or a config copy, because the panel may be built during route
 * registration at boot, before a consumer toggles the gate. Reading the
 * panel here lets a consumer enable or disable the gate at any time without
 * re-registering routes:
 *
 * - The gate is **enabled** only while its own middleware class appears in the
 *   panel's `authMiddleware()` list. When listed, the request must authenticate
 *   against the panel's auth guard (`->authGuard()`) before anything the
 *   panel serves is reachable; an unauthenticated shell request is redirected
 *   to the panel's `loginUrl`, and an unauthenticated JSON/API request gets a
 *   401 (the framework's `AuthenticationException` handling — the endpoints
 *   are never reachable without a session either).
 * - Otherwise it passes every request straight through — the permissive
 *   default that keeps the workbench (and the panel itself) open.
 *
 * This mirrors Filament's `authMiddleware()` opt-in and `Authenticate`:
 * enrolling the gate is the switch, and the guard/login target are pure panel
 * config resolved per request, never a cached copy.
 *
 * Two mount styles share the class:
 *
 * - **Gate** (`authMiddleware([Authenticate::class])`, no guard param) — the
 *   permissive opt-in described above: enforcement follows the live panel's
 *   `authMiddleware()` list.
 * - **Auth routes** (`Authenticate:{$guard}` in `routes/auth.php`, guard param
 *   passed) — always enforced. The panel's own authenticated auth surface
 *   (profile, password confirmation, two-factor management) must require a
 *   session regardless of the gate toggle, and the same `redirectTo()`
 *   sends the visitor to the panel's login page.
 */
class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards): mixed
    {
        // Gate usage passes no guard and follows the live panel's auth
        // middleware list (permissive default: without the gate listed, the
        // shell pages serve openly — workbench mode). Auth-route usage passes
        // the guard explicitly and always enforces. The panel resolves per
        // request, so a consumer toggling ->authMiddleware() needs no route
        // re-registration.
        if (empty($guards) && ! in_array(self::class, app(Refilament::class)->panel()->getAuthMiddleware(), true)) {
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
        $panel = app(Refilament::class)->panel();
        $guard = $this->auth->guard($panel->getAuthGuard());

        if ($guard->check()) {
            $this->auth->shouldUse($panel->getAuthGuard());

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
        return app(Refilament::class)->panel()->getLoginUrl();
    }
}
