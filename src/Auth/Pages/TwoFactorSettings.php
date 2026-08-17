<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Refilament\Refilament\Refilament;

/**
 * The panel's first-party two-factor management page (the "enable / disable,
 * QR code, recovery codes" UI) — the Refilament analogue of Filament's
 * two-factor section on the Profile page, served as its own standalone page.
 *
 * Unlike the guest-facing auth pages (Login, TwoFactorChallenge), this page
 * is for an *authenticated* user managing their own two-factor settings, so it
 * renders inside the panel shell (`refilament/two-factor-settings`) and is
 * mounted behind the panel's auth guard. All the actual machinery — the
 * enable/disable endpoints, QR + secret-key JSON, recovery codes, and the
 * password-confirmation guard that protects them — is Fortify's, reached
 * through the `user/two-factor-*` routes; this page only decides what the
 * authenticated user sees and which state to hand the React component.
 *
 * The page reports two booleans computed from the user's two-factor columns:
 * `enabled` (a confirmed secret) and `enabling` (a secret generated but not
 * yet confirmed — the mid-setup state after the user pressed "Enable" but
 * hasn't scanned the QR code yet). The React component needs both to choose
 * which of its three states to render.
 */
class TwoFactorSettings extends AuthPage
{
    public static function getComponent(): string
    {
        return 'refilament/two-factor-settings';
    }

    public static function getPath(): string
    {
        return '/user/two-factor';
    }

    /**
     * The user's two-factor columns (`two_factor_secret`,
     * `two_factor_confirmed_at`) are declared by the
     * `TwoFactorAuthenticatable` trait as model attributes, not as typed
     * class properties — so PHPStan needs an assertion to see them.
     *
     * @return array<string, bool>
     */
    public static function getViewData(Request $request): array
    {
        $panel = app(Refilament::class)->panel();
        $user = Auth::guard($panel->getAuthGuard())->user();

        if ($user === null) {
            return ['enabled' => false, 'enabling' => false];
        }

        /** @var object{two_factor_secret?: mixed, two_factor_confirmed_at?: mixed} $user */
        $u = $user;

        $secret = $u->two_factor_secret ?? null;

        return [
            'enabled' => $secret !== null && ($u->two_factor_confirmed_at ?? null) !== null,
            'enabling' => $secret !== null && ($u->two_factor_confirmed_at ?? null) === null,
        ];
    }
}
