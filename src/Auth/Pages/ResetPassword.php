<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;

class ResetPassword extends AuthPage
{
    public static function getComponent(): string
    {
        return 'auth/reset-password';
    }

    public static function getPath(): string
    {
        return '/reset-password/{token}';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(Request $request): array
    {
        return [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ];
    }
}
