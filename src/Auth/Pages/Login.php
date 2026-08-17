<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;
use Refilament\Refilament\Auth\Pages\Concerns\CanShowPasswordResetLink;

class Login extends AuthPage
{
    use CanShowPasswordResetLink;

    public static function getComponent(): string
    {
        return 'auth/login';
    }

    public static function getPath(): string
    {
        return '/login';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(Request $request): array
    {
        return [
            'canResetPassword' => static::canShowPasswordResetLink(),
        ];
    }
}
