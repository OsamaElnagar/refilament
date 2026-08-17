<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Refilament\Refilament\Refilament;

/**
 * The panel's first-party profile page (Filament's `->profile()`) — the
 * EditProfile surface where the authenticated user updates their name, email,
 * and password, plus manages two-factor authentication when the panel has it
 * enabled.
 *
 * Renders inside the panel shell (`refilament/edit-profile`) and is mounted
 * behind the panel's auth guard. Fortify's `PUT /user/profile-information`
 * and `PUT /user/password` endpoints (already registered in routes/auth.php)
 * power the profile and password updates; the two-factor management section
 * uses the same endpoints and flows as the standalone `TwoFactorSettings` page.
 *
 * Server props carry the current user's name and email (for form pre-fill),
 * plus the two-factor state so the React page can embed the 2FA management
 * section alongside the profile/password forms.
 */
class EditProfile extends AuthPage
{
    public static function getComponent(): string
    {
        return 'refilament/edit-profile';
    }

    public static function getPath(): string
    {
        return '/user/profile';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(Request $request): array
    {
        $panel = app(Refilament::class)->panel();
        $user = Auth::guard($panel->getAuthGuard())->user();

        /** @var object{name?: string, email?: string, two_factor_secret?: mixed, two_factor_confirmed_at?: mixed} $user */
        $u = $user;

        $data = [
            'name' => $u->name ?? '',
            'email' => $u->email ?? '',
        ];

        if ($panel->hasTwoFactorAuthentication()) {
            $secret = $u->two_factor_secret ?? null;

            $data['twoFactor'] = [
                'enabled' => $secret !== null && ($u->two_factor_confirmed_at ?? null) !== null,
                'enabling' => $secret !== null && ($u->two_factor_confirmed_at ?? null) === null,
            ];
        }

        return $data;
    }
}
