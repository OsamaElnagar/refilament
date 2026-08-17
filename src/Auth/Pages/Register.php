<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

class Register extends AuthPage
{
    public static function getComponent(): string
    {
        return 'auth/register';
    }

    public static function getPath(): string
    {
        return '/register';
    }
}
