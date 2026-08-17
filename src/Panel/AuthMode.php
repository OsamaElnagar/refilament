<?php

declare(strict_types=1);

namespace Refilament\Refilament\Panel;

/**
 * How the panel owns authentication (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md).
 *
 * - `Standalone` — the default. The panel registers its own Fortify-backed
 *   auth surface (login, register, password reset, 2FA, profile, ...) under
 *   its own URL prefix, with panel-scoped route names
 *   (`refilament.{panelId}.auth.*`). Needed when the panel uses its own guard
 *   or serves a separate set of users.
 * - `Shared` — the panel defers entirely to the app's existing auth: it
 *   registers no auth routes of its own and the auth gate redirects
 *   unauthenticated visitors to the app's `login` route (Laravel's standard
 *   `guest()`/`intended()` flow carries the visitor back to the page they
 *   wanted). By construction this cannot collide with an app that also uses
 *   Fortify — there is nothing to collide. Opt-in via `Panel::authMode()`;
 *   enabling any panel-owned auth page in shared mode throws.
 */
enum AuthMode: string
{
    case Standalone = 'standalone';

    case Shared = 'shared';

    public function isStandalone(): bool
    {
        return $this === self::Standalone;
    }

    public function isShared(): bool
    {
        return $this === self::Shared;
    }
}
