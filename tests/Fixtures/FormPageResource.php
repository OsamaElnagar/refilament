<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A fixture resource whose pages map includes a custom page hosting a form
 * (FormResourcePage) — exercising the page-forms slice on the resource-page
 * side: the form payload merges into getPayload() and the schema resolver
 * registers for the typed submit endpoint.
 */
class FormPageResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'form-page';

    public static function table(Table $table): Table
    {
        return $table->id(static::getTableId())->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('form-page-form');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'form' => FormResourcePage::route('/form'),
        ];
    }
}
