<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A resource whose form declares fields but NO submitUsing() handler — the
 * auto-create default (slice 2.6) must fall back to `Post::create($data)`.
 * Deliberately separate from DemoResource, whose minimal form and defaults
 * other tests assert on.
 */
class AutoCreateResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    public static function table(Table $table): Table
    {
        return $table
            ->id(static::getTableId())
            ->columns([
                Column::make('id')->label('ID'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->id(static::getFormId())
            ->components([
                TextInput::make('title')->label('Title')->required(),
                TextInput::make('slug')->label('Slug')->required(),
                TextInput::make('author')->label('Author')->required(),
                TextInput::make('status')->label('Status')->required(),
            ]);
    }
}
