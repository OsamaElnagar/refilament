<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;

class EmailVerificationPrompt extends AuthPage
{
    public static function getComponent(): string
    {
        return 'auth/verify-email';
    }

    public static function getPath(): string
    {
        return '/email/verify';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(Request $request): array
    {
        return [
            'status' => $request->session()->get('status'),
        ];
    }
}
