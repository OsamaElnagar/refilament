<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * Fixture table for the modal-create tests (slice 1.1). The workbench
 * resource tables no longer ship a table-level create modal — the default
 * page-header CreateAction owns the create button on resource list pages
 * (slice 1.10) — but the `Table::headerActions()` surface stays first-party
 * for relation managers and embedded tables, so it keeps dedicated coverage
 * here.
 */
class ModalPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->id('modal-posts')
            ->heading('Modal posts')
            ->query(Post::query())
            ->columns([
                Column::make('id')->label('ID'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('New Modal Post')
                    ->type('create')
                    ->schema('post-form'),
            ]);
    }
}
