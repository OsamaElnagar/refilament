<?php

declare(strict_types=1);

namespace Refilament\Refilament\Facades;

use BackedEnum;
use Illuminate\Support\Facades\Facade;
use Refilament\Refilament\Support\Icons\IconManager;

/**
 * @method static string | BackedEnum | null resolve(string | array<string> $alias)
 *
 * @see IconManager
 */
class RefilamentIcon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IconManager::class;
    }

    /**
     * @param  array<string, string | BackedEnum>  $icons
     */
    public static function register(array $icons): void
    {
        static::resolved(function (IconManager $iconManager) use ($icons): void {
            $iconManager->register($icons);
        });
    }
}
