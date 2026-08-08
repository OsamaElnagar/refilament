<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Tables;

use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Tables\Action;
use Refilament\Refilament\Tables\BulkAction;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\ForceDeleteBulkAction;
use Refilament\Refilament\Tables\Group;
use Refilament\Refilament\Tables\RestoreBulkAction;
use Refilament\Refilament\Tables\SelectFilter;
use Refilament\Refilament\Tables\Summarizers\Sum;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tables\TextFilter;
use Refilament\Refilament\Tables\TrashedFilter;
use Workbench\App\Models\Post;

/**
 * The posts table definition, extracted into its own class so any component
 * can compose it — the resource delegates to it today, a relation manager
 * reuses it later. This is the production pattern proven by the Ahram ERP
 * (`AccountsTable::configure($table)`): a plain static factory, not a
 * subclass, called from `Resource::table()` and reused verbatim elsewhere
 * (docs/ARCHITECTURE.md, "Relation managers & reusable table/form classes").
 *
 * The serialized `id` below drives the client's fetch URLs, so it must
 * always match `PostResource::$tableId`.
 */
class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->id('posts')
            ->heading('Posts')
            ->recordsPerPage(10)
            ->recordsPerPageSelectOptions([10, 25, 50])
            ->defaultSort('published_at', 'desc')
            // Record grouping (slice 2.3): register the status group so the
            // toolbar "Group" dropdown appears. Grouping is opt-in — enabled
            // by picking a group (or the `group` query param) — so the
            // table's default sort/pagination tests stay untouched.
            ->groups([
                Group::make('status')->label('Status')->collapsible(),
            ])
            // Record selection (slice 2.2): enables the checkbox column and the
            // toolbar carrying the bulk actions below.
            ->selectable()
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->multiple(),
                TextFilter::make('title')
                    ->label('Title')
                    ->placeholder('Filter by title…'),
                // Soft-delete view (slice 2.2): Posts uses SoftDeletes, so the
                // table exposes the trash toggle ("With trashed" / "Only
                // trashed"), which unlocks the restore + force-delete toolbar
                // actions below.
                TrashedFilter::make()->label('Trashed'),
            ])
            // The modal create action (slice 1.1): the "New Post" button
            // opens the linked form in a dialog, submits it through the typed
            // submit endpoint, and refreshes the table on success.
            ->headerActions([
                Action::make('create')
                    ->label('New Post')
                    ->type('create')
                    ->schema('post-form'),
            ])
            ->actions([
                // Modal edit (slice 1.2): the client fetches this record's
                // values (GET .../record/{id}?schema=post-form), opens the
                // form pre-filled, and submits through this action endpoint
                // with { record, data } — validated against the linked
                // schema's rules server-side before the closure runs.
                Action::make('edit')
                    ->label('Edit')
                    ->type('edit')
                    ->schema('post-form')
                    ->successMessage('Post updated.')
                    ->action(static fn (Post $record, array $data): mixed => $record->update($data)),
                Action::make('publish')
                    ->label('Publish')
                    ->color('success')
                    ->successMessage('Post published.')
                    ->visible(static fn (Post $record): bool => $record->status !== 'published')
                    ->action(static fn (Post $record): mixed => $record->update(['status' => 'published'])),
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->successMessage('Post deleted.')
                    ->action(static fn (Post $record): mixed => $record->delete()),
            ])
            // Toolbar (bulk) actions (slice 2.2): they render in a selection
            // toolbar only while rows are selected, and run once against the
            // whole selected set through the typed bulk endpoint.
            ->toolbarActions([
                BulkAction::make('delete')
                    ->label('Delete selected')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->successMessage('Selected posts deleted.')
                    ->successNotification(
                        Notification::make()
                            ->title('Posts deleted.')
                            ->body('The selected posts were removed.')
                            ->danger(),
                    )
                    ->action(static function ($records): void {
                        $records->each(fn ($record): mixed => $record->delete());
                    }),
                // Soft-delete convenience actions (slice 2.2): restore (+
                // force delete) operate on trashed rows. They are reachable
                // once the Trashed filter is set to "Only trashed" (restore)
                // or "With trashed" (force delete), and resolve the selected
                // trashed records server-side.
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ])
            ->columns([
                Column::make('id')->label('ID')->sortable(),
                Column::make('title')->label('Title')->sortable()->searchable(),
                Column::make('author')->searchable()->toggleable(),
                // Badge + per-record color (Slice 2.1): the status renders as
                // a shadcn Badge tinted server-side by color(), which maps the
                // raw state to a color — Filament's canonical status idiom.
                Column::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'draft' => 'secondary',
                    'published' => 'success',
                    'archived' => 'warning',
                    default => 'secondary',
                }),
                // Relationship column via dot-notation (Slice 2.1): resolves
                // the related attribute server-side through data_get — no
                // getStateUsing() needed. Eager-loaded in query() below.
                Column::make('user.name')->label('User')->toggleable(),
                Column::make('views')
                    ->label('Views')
                    ->sortable()
                    ->toggleable()
                    // Footer summary (slice 1.7): the total views across the
                    // filtered result set, computed server-side and rendered
                    // as the table's footer row.
                    ->summarize(Sum::make()->label('Total views')->numeric()),
                // Date formatting (Slice 2.1): the published_at attribute
                // serializes pre-formatted through Carbon.
                Column::make('published_at')->label('Published')->placeholder('—')->sortable()->toggleable()->date(),
            ])
            // Eager-load the user relation so the dot-notation resolver (and
            // the getStateUsing() of older slices) never triggers an N+1
            // query per row.
            ->query(Post::query()->with('user'));
    }
}
