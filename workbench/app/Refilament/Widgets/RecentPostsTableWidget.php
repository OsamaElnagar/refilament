<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Widgets;

use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Widgets\TableWidget;
use Workbench\App\Models\Post;

/**
 * A widget that is itself a table (slice D1) — the Ahram
 * `RecentSalesInvoicesTable` idiom. The table is a plain reusable class (the
 * same convention Resource::table() uses); the widget hosts its first page,
 * and sorting/pagination go through the typed table endpoint (the widget's
 * table is registered under its id for that — see WorkbenchServiceProvider).
 */
class RecentPostsTableWidget extends TableWidget
{
    public static function table(Table $table): Table
    {
        return $table
            ->query(Post::query())
            ->recordsPerPage(5)
            ->defaultSort('created_at', 'desc')
            ->columns([
                Column::make('title')->label('Title')->sortable()->searchable(),
                Column::make('author')->searchable(),
                Column::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'archived' => 'warning',
                        default => 'secondary',
                    }),
                Column::make('views')->label('Views')->sortable(),
                Column::make('created_at')->label('Created')->date('Y-m-d')->sortable(),
            ]);
    }
}
