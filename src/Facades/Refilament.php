<?php

declare(strict_types=1);

namespace Refilament\Refilament\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Refilament\Refilament\Refilament
 */
class Refilament extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Refilament\Refilament\Refilament::class;
    }
}
