<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

class RequestPasswordReset extends AuthPage
{
    public static function getComponent(): string
    {
        return 'auth/forgot-password';
    }

    public static function getPath(): string
    {
        return '/forgot-password';
    }
}
