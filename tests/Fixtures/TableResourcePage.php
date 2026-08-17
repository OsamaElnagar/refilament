<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Pages\Page;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A custom resource page hosting a table (the pages-as-tables slice) — the
 * resource-page analogue of a standalone report page. Its table payload rides
 * on the page's getPayload() via serializePageTable(), and its table resolver
 * registers under getTableId() so the typed table endpoints work.
 */
class TableResourcePage extends Page
{
    /** @var class-string<TablePageResource>|null */
    protected static ?string $resource = TablePageResource::class;

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-table';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Post::query())
            ->columns([
                Column::make('title')->label('Title')->searchable()->sortable(),
                Column::make('status')->label('Status')->sortable(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(string $resource): array
    {
        return [
            'description' => 'A custom resource page hosting a table.',
        ];
    }
}
