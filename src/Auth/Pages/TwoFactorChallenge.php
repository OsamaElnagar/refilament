<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

class TwoFactorChallenge extends AuthPage
{
    public static function getComponent(): string
    {
        return 'auth/two-factor-challenge';
    }

    public static function getPath(): string
    {
        return '/two-factor-challenge';
    }
}
