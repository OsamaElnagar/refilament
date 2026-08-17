<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;

/**
 * The panel's first-party password-confirmation page — Fortify's
 * `password.confirm` route rendered as an Inertia component instead of a
 * Blade view. Fortify guards the sensitive account-management endpoints
 * (2FA enable/disable/regenerate, profile/password updates when configured)
 * with the `password.confirm` middleware, which redirects to this route when
 * the session's confirmation window has lapsed. The React page posts the
 * password to `user/confirm-password` and, on success, carries the visitor to
 * the intended `next` URL — the standard Fortify round trip, translated for
 * the Inertia shell.
 */
class ConfirmPassword extends AuthPage
{
    public static function getComponent(): string
    {
        return 'auth/confirm-password';
    }

    public static function getPath(): string
    {
        return '/user/confirm-password';
    }

    /**
     * The URL the visitor was heading to when the password-confirmation
     * middleware bumped them here — handed back to the React page so its
     * submit can continue the original request.
     *
     * @return array<string, string>
     */
    public static function getViewData(Request $request): array
    {
        return [
            'next' => (string) $request->query('next', ''),
        ];
    }
}
