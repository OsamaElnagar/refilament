<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Pages;

use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\SelectFilter;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A standalone panel page hosting a table (the pages-as-tables slice) — the
 * report/dashboard idiom: a page whose payload IS a table payload. The page
 * declares `table()` (query + columns + filters); everything else is wired
 * by the package — the table resolver registers under getTableId(), the
 * payload serializes through serializePageTable(), and the generic
 * refilament/page-table component renders it, so this class is the whole
 * feature. Pagination, sorting, search and filters all run through the typed
 * table endpoints server-side. Served at /refilament/posts-table by the
 * shared PanelPageController.
 */
class PostsTablePage extends Page
{
    protected static ?string $navigationLabel = 'Posts table';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?int $navigationSort = 98;

    protected static bool $shouldRegisterNavigation = true;

    public static function getSlug(): string
    {
        return 'posts-table';
    }

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-table';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Post::query())
            ->defaultSort('published_at', 'desc')
            ->recordsPerPageSelectOptions([5, 10, 25])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->multiple(),
            ])
            ->columns([
                Column::make('title')->label('Title')->searchable()->sortable(),
                Column::make('author')->label('Author')->searchable()->sortable(),
                Column::make('status')->label('Status')->badge()->sortable(),
                Column::make('views')->label('Views')->numeric()->sortable(),
                Column::make('published_at')->label('Published')->date()->sortable(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [
            'description' => 'A page hosting a table — the report/dashboard idiom. Pagination, sorting, search and the status filter all run through the typed table endpoints.',
        ];
    }
}
