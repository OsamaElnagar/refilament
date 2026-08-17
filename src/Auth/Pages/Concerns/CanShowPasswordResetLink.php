<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages\Concerns;

use Laravel\Fortify\Features;
use Refilament\Refilament\Refilament;

/**
 * Whether the login page links to the forgot-password flow — shown only
 * while the panel's password-reset pages are enabled and Fortify's
 * reset-password feature is on (mirrors the starter-kit login page's
 * `canResetPassword` prop).
 */
trait CanShowPasswordResetLink
{
    public static function canShowPasswordResetLink(): bool
    {
        return app(Refilament::class)->panel()->hasPasswordReset()
            && Features::enabled(Features::resetPasswords());
    }
}
