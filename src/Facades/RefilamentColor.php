<?php

declare(strict_types=1);

namespace Refilament\Refilament\Facades;

use Illuminate\Support\Facades\Facade;
use Refilament\Refilament\Support\Colors\ColorManager;

/**
 * @method static \Refilament\Refilament\Support\Colors\ColorManager register(array<string, array<int, string>|string>|\Closure $colors)
 * @method static array<string, array<int, string>> getColors()
 * @method static ?array<int, string> getColor(string $color)
 * @method static void overrideShades(string $alias, array<int> $shades)
 * @method static void addShades(string $alias, array<int> $shades)
 * @method static void removeShades(string $alias, array<int> $shades)
 *
 * @see ColorManager
 */
class RefilamentColor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ColorManager::class;
    }
}
