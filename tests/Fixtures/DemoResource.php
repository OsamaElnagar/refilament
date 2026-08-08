<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

class DemoResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    public static function table(Table $table): Table
    {
        return $table
            ->id(static::getTableId())
            ->columns([
                Column::make('id')->label('ID'),
                Column::make('title')->label('Title')->searchable(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->id(static::getFormId())
            ->components([
                TextInput::make('title')->label('Title')->default('Hello'),
                TextInput::make('status')->label('Status'),
            ]);
    }
}
