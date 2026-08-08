<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Tables;

use Refilament\Refilament\Tables\Action;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\SelectFilter;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Comment;

/**
 * The comments table definition, extracted into the relation manager composing
 * this class (the owner-scoped query is supplied afterwards), so `configure()`
 * never declares a model-level `query()`. This is the reusable-class pattern
 * proven by the posts demo (docs/ARCHITECTURE.md, "Relation classes and
 * reusable table/form classes"): the same table defines any owner's comments.
 *
 * The create/edit actions link the standalone CommentsForm schema; the typed
 * relation action endpoint validates against that form and scopes every change
 * (and every created record) to the owner.
 */
class CommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Comments')
            ->recordsPerPage(10)
            ->recordsPerPageSelectOptions([5, 10, 25])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('is_visible')
                    ->label('Visibility')
                    ->options([
                        1 => 'Visible',
                        0 => 'Hidden',
                    ]),
            ])
            // Modal create (slice 1.8): the "Add comment" header button opens
            // the linked comment form; submit creates the comment as a record of
            // the owner post (the relation sets the foreign key).
            ->headerActions([
                Action::make('create')
                    ->label('Add comment')
                    ->type('create')
                    ->schema('comment-form')
                    ->successMessage('Comment added.'),
            ])
            ->actions([
                // Modal edit: like the posts table, validated against the
                // comment form before the closure runs.
                Action::make('edit')
                    ->label('Edit')
                    ->type('edit')
                    ->schema('comment-form')
                    ->successMessage('Comment updated.')
                    ->action(static fn (Comment $record, array $data): mixed => $record->update($data)),
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->successMessage('Comment deleted.')
                    ->action(static fn (Comment $record): mixed => $record->delete()),
            ])
            ->columns([
                Column::make('id')->label('ID')->sortable(),
                Column::make('title')->label('Title')->sortable()->searchable(),
                Column::make('content')->label('Content')->searchable(),
                Column::make('is_visible')->label('Visible')->sortable(),
            ]);
    }
}
