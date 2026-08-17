<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Enums;

enum Platform
{
    case Windows;

    case Linux;

    case Mac;

    case Other;

    public static function detect(?string $userAgent = null): Platform
    {
        $userAgent = $userAgent ?? request()->userAgent() ?? '';

        return match (true) {
            str_contains($userAgent, 'Windows') => self::Windows,
            str_contains($userAgent, 'Mac') => self::Mac,
            str_contains($userAgent, 'Linux') => self::Linux,
            default => self::Other,
        };
    }
}
