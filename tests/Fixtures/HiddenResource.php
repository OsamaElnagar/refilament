<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;

class HiddenResource extends Resource
{
    // No [$model] — proves getModel() fails fast, and discovery skips the
    // class entirely via isDiscovered().

    protected static bool $isDiscovered = false;

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }
}
