<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * The edit-less counterpart — the resource registers a view page only, so an
 * EditAction declared on it has no page route to navigate to (and, lacking
 * the modal fallback, is dropped rather than rendered dead).
 */
class RecordActionsNoEditResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'record-actions-no-edit';

    public static function table(Table $table): Table
    {
        return $table->id(static::getTableId())->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('record-actions-no-edit-form');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'view' => RecordActionsNoEditViewPage::route('/{record}'),
        ];
    }
}
