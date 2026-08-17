<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Tables;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\ActionGroup;
use Refilament\Refilament\Actions\DeleteAction;
use Refilament\Refilament\Actions\DeleteBulkAction;
use Refilament\Refilament\Actions\ForceDeleteBulkAction;
use Refilament\Refilament\Actions\RestoreBulkAction;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Columns\ColorColumn;
use Refilament\Refilament\Tables\Columns\ImageColumn;
use Refilament\Refilament\Tables\Columns\SelectColumn;
use Refilament\Refilament\Tables\Columns\TagsColumn;
use Refilament\Refilament\Tables\Columns\TextInputColumn;
use Refilament\Refilament\Tables\Columns\ToggleColumn;
use Refilament\Refilament\Tables\Group;
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
                // Relationship filter (slice: relationship filters): options
                // resolve from the related User records (id => name, ordered
                // by name), and applying the filter constrains the query with
                // whereHas against those keys.
                SelectFilter::make('user')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->multiple(),
            ])
            // No table-level header actions here: the resource list page's
            // default page-header CreateAction owns the create button (slice
            // 1.10 — it navigates to /posts/create). The table-level modal
            // surface stays for relation managers and embedded tables (the
            // comments relation manager + the ModalPostsTable fixture use it).
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
                // Action group (professional actions slice): the secondary row
                // actions collapse into an overflow menu — the ellipsis button
                // in the row's actions column. Members keep per-record
                // visibility (publish shows only for non-published posts) and
                // are resolved by name through the same action endpoint.
                ActionGroup::make()
                    ->label('More')
                    ->actions([
                        Action::make('publish')
                            ->label('Publish')
                            ->icon('check-circle')
                            ->color('success')
                            ->successMessage('Post published.')
                            ->visible(static fn (Post $record): bool => $record->status !== 'published')
                            ->action(static fn (Post $record): mixed => $record->update(['status' => 'published'])),
                        Action::make('archive')
                            ->label('Archive')
                            ->icon('archive')
                            ->color('warning')
                            ->successMessage('Post archived.')
                            ->visible(static fn (Post $record): bool => $record->status === 'published')
                            ->action(static fn (Post $record): mixed => $record->update(['status' => 'archived'])),
                    ]),
                // Built-in delete (professional actions slice): DeleteAction
                // carries Filament's defaults — trash icon, danger color,
                // confirmation prompt, per-record `delete` policy check and
                // `$record->delete()` — so consumers never hand-wire them.
                // Fluent calls after make() override any default.
                DeleteAction::make()
                    ->successMessage('Post deleted.'),
            ])
            // Toolbar (bulk) actions (slice 2.2): they render in a selection
            // toolbar only while rows are selected, and run once against the
            // whole selected set through the typed bulk endpoint.
            ->toolbarActions([
                // Built-in bulk delete (professional actions slice): per-record
                // `delete` policy authorization (unauthorized records are
                // filtered out before anything is deleted), confirmation and a
                // standard success toast. The custom notification below still
                // wins over the default message when set.
                DeleteBulkAction::make()
                    ->successNotification(
                        Notification::make()
                            ->title('Posts deleted.')
                            ->body('The selected posts were removed.')
                            ->danger(),
                    ),
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
                // Inline-editable toggle (slice: editable columns): the switch
                // posts `status` = 'published'/'draft' through the record-column
                // endpoint. getStateUsing() derives the switch state from the
                // string status; updateStateUsing() writes it back as a string
                // (the default handler would mass-assign the raw boolean, which
                // is wrong for this column) - the honest, stateless rebuild of
                // Filament's Livewire toggle.
                ToggleColumn::make('published')
                    ->label('Published')
                    ->onColor('success')
                    ->getStateUsing(fn (Post $record): bool => $record->status === 'published')
                    ->updateStateUsing(fn (Post $record, bool $state): mixed => $record->update([
                        'status' => $state ? 'published' : 'draft',
                    ])),
                // Inline-editable select (slice: editable columns): a compact
                // native `<select>` over the status options that posts its
                // choice through the record-column endpoint. getStateUsing()
                // reads the status string; updateStateUsing() writes it back.
                SelectColumn::make('status_select')
                    ->label('Set Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->placeholder('Choose…')
                    ->getStateUsing(fn (Post $record): string => $record->status)
                    ->updateStateUsing(fn (Post $record, string $state): mixed => $record->update(['status' => $state])),
                // Inline-editable text (slice: editable columns): a compact
                // number input that commits on Enter/blur through the
                // record-column endpoint. getStateUsing() reads the views
                // count; updateStateUsing() casts the submitted string to an
                // integer.
                TextInputColumn::make('views_edit')
                    ->label('Edit Views')
                    ->type('number')
                    ->step(1)
                    ->getStateUsing(fn (Post $record): int => $record->views)
                    ->updateStateUsing(fn (Post $record, mixed $state): mixed => $record->update([
                        'views' => (int) $state,
                    ])),
                // Relationship column via dot-notation (Slice 2.1): resolves
                // the related attribute server-side through data_get - no
                // getStateUsing() needed. Eager-loaded in query() below.
                // Sortable + searchable (Slice 2.1): a relationship column can
                // now be sorted by a correlated subquery over the related
                // table and searched via Eloquent's native whereRelation.
                Column::make('user.name')->label('User')->sortable()->searchable()->toggleable(),
                Column::make('views')
                    ->label('Views')
                    ->sortable()
                    ->toggleable()
                    // Footer summary (slice 1.7): the total views across the
                    // filtered result set, computed server-side and rendered
                    // as the table's footer row.
                    ->summarize(Sum::make()->label('Total views')->numeric()),
                // Macro demo (production reference §1.2): `egp()` is defined
                // once in RefilamentDefaultsServiceProvider and used here as a
                // first-class column verb — ad revenue at $0.10 per view.
                Column::make('revenue')
                    ->label('Revenue')
                    ->getStateUsing(fn (Post $record): float => (float) $record->views * 0.1)
                    ->egp(),
                // Date formatting (Slice 2.1): the published_at attribute
                // serializes pre-formatted through Carbon.
                Column::make('published_at')->label('Published')->placeholder('—')->sortable()->toggleable()->date(),
                // Tags column (slice §1.2): the state renders as a badge list,
                // capped at three with an overflow count.
                TagsColumn::make('tags')
                    ->label('Tags')
                    ->limitList(3)
                    ->getStateUsing(fn (Post $record): array => array_slice(
                        array_values(array_filter(explode(' ', (string) $record->title))),
                        0,
                        4,
                    )),
                // Image column (slice §1.3): stacked circular avatars derived
                // from the record's id; URLs only in v1.
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->stacked()
                    ->circular()
                    ->ring(2)
                    ->getStateUsing(fn (Post $record): array => [
                        "https://i.pravatar.cc/40?u={$record->getKey()}-1",
                        "https://i.pravatar.cc/40?u={$record->getKey()}-2",
                        "https://i.pravatar.cc/40?u={$record->getKey()}-3",
                    ]),
                // Color column (slice §1.4): a swatch of the status colour,
                // copyable on click.
                ColorColumn::make('status_color')
                    ->label('Colour')
                    ->copyable()
                    ->getStateUsing(fn (Post $record): string => match ($record->status) {
                        'published' => '#10b981',
                        'archived' => '#f59e0b',
                        default => '#94a3b8',
                    }),
            ])
            // Eager-load the user relation so the dot-notation resolver (and
            // the getStateUsing() of older slices) never triggers an N+1
            // query per row.
            ->query(Post::query()->with('user'));
    }
}
