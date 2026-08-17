<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A fixture resource whose pages map includes a custom page hosting a table
 * (TableResourcePage) — exercising the pages-as-tables slice on the
 * resource-page side: the table payload merges into getPayload() and the
 * table resolver registers for the typed table endpoints.
 */
class TablePageResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'table-page';

    public static function table(Table $table): Table
    {
        return $table->id(static::getTableId())->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('table-page-form');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'report' => TableResourcePage::route('/report'),
        ];
    }
}
