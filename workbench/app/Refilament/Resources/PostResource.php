<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Infolists\Components\CodeEntry;
use Refilament\Refilament\Infolists\Components\ColorEntry;
use Refilament\Refilament\Infolists\Components\ImageEntry;
use Refilament\Refilament\Infolists\Components\KeyValueEntry;
use Refilament\Refilament\Infolists\Components\RepeatableEntry;
use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Infolists\Components\ViewEntry;
use Refilament\Refilament\Resources\Pages\PageRegistration;
use Refilament\Refilament\Resources\RelationManagers\RelationManager;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Resources\Pages\ListPosts;
use Workbench\App\Refilament\Resources\Pages\PostManagePage;
use Workbench\App\Refilament\Resources\Pages\PostStats;
use Workbench\App\Refilament\Resources\RelationManagers\CommentsRelationManager;
use Workbench\App\Refilament\Schemas\PostsForm;
use Workbench\App\Refilament\Tables\PostsTable;

/**
 * The demo posts resource. Generated with `refilament:make-resource Post
 * --model=Workbench\App\Models\Post --generate`, then customized: explicit
 * ids, and the table + form extracted into standalone `PostsTable` /
 * `PostsForm` classes this resource simply composes (slice 1.3 — the
 * reusable-class pattern, docs/ARCHITECTURE.md).
 */
class PostResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    // The demo routes, endpoints and tests already use these ids.
    protected static ?string $tableId = 'posts';

    protected static ?string $formId = 'post-form';

    // The model attribute that headlines a post in global search results
    // (slice 3.5). Falls back to the table's searchable columns otherwise.
    protected static ?string $recordTitleAttribute = 'title';

    /**
     * A demo of the global search override points (slice 3.5): every result
     * for a post shows a status detail line, and archived posts drop below
     * live ones in the result ordering via a lighter global search sort.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(mixed $record): array
    {
        return [
            'status' => (string) $record->getAttribute('status'),
        ];
    }

    /**
     * Result actions (slice 3.5) — the default edit link, now carrying an
     * icon and tooltip so the search dialog's buttons render them (the last
     * 3.5 deferral). The canEdit gate is preserved from the parent default.
     *
     * @return array<int, Action>
     */
    public static function getGlobalSearchResultActions(mixed $record): array
    {
        if (! static::canEdit($record)) {
            return [];
        }

        return [
            Action::make('edit')
                ->label('Edit')
                ->icon('pencil')
                ->tooltip('Edit this post')
                ->url(route('refilament.resource.edit', [
                    'resource' => static::getTableId(),
                    'record' => $record->getKey(),
                ])),
        ];
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function form(Schema $schema): Schema
    {
        return PostsForm::configure($schema);
    }

    /**
     * The read-only infolist (slice 3.3) — replaces the plain column list on
     * the view page with a tailored record read-out. Entries reuse the same
     * server-side formatting (date / numeric / badge / color) as the table
     * columns, so the view page and list present the record consistently.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Title'),
            TextEntry::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'draft' => 'secondary',
                    'published' => 'success',
                    'archived' => 'warning',
                    default => 'secondary',
                }),
            TextEntry::make('user.name')->label('Author'),
            TextEntry::make('author')
                ->label('Byline')
                ->icon('user')
                ->iconColor('info'),
            TextEntry::make('views')->label('Views')->numeric(),
            TextEntry::make('published_at')->label('Published')->date()->placeholder('—'),

            // The read-only entry gallery (slice 3.9 / PLAN §2) — color,
            // image, key-value, code and embedded-view entries, resolved
            // server-side from the record at payload time.
            Grid::make()->columns(2)->schema([
                ColorEntry::make('accent_color')
                    ->label('Accent color')
                    ->getStateUsing(fn (): string => '#6366f1')
                    ->copyable(),

                ColorEntry::make('semantic_colors')
                    ->label('Semantic colors')
                    ->getStateUsing(fn (): array => ['#22c55e', '#ef4444', '#eab308'])
                    ->copyable(),

                ImageEntry::make('gallery')
                    ->label('Gallery')
                    ->getStateUsing(fn (): array => [
                        'https://picsum.photos/seed/refilament-1/200',
                        'https://picsum.photos/seed/refilament-2/200',
                        'https://picsum.photos/seed/refilament-3/200',
                    ])
                    ->size(48)
                    ->circular()
                    ->stacked()
                    ->limit(2),

                KeyValueEntry::make('meta')
                    ->label('Meta')
                    ->getStateUsing(fn (): array => [
                        'theme' => 'dark',
                        'language' => 'en',
                        'author' => 'Ada',
                    ])
                    ->keyLabel('Setting')
                    ->valueLabel('Value'),

                CodeEntry::make('snippet')
                    ->label('Snippet')
                    ->getStateUsing(fn ($record): string => '<h1>'.e($record?->getAttribute('title'))."</h1>\n<p>Published post</p>")
                    ->language('html')
                    ->lineNumbers()
                    ->copyable(),

                ViewEntry::make('playground-callout')
                    ->viewData([
                        'title' => 'Infolist entries',
                        'body' => 'Color, image, key-value, code and embedded-view entries — resolved server-side from the bound record.',
                    ]),
            ]),

            // Repeatable entry (slice: RepeatableEntry / PLAN §3) — a read-only
            // list. Each item (a per-word breakdown of the title) renders
            // through the child entry schema, resolved server-side against the
            // item's own array data.
            RepeatableEntry::make('word_bag')
                ->label('Word breakdown')
                ->getStateUsing(fn (Post $record): array => collect(explode(' ', (string) $record->title))
                    ->filter()
                    ->map(fn (string $word): array => ['word' => $word, 'length' => mb_strlen($word)])
                    ->values()
                    ->all())
                ->schema([
                    TextEntry::make('word')->label('Word'),
                    TextEntry::make('length')->label('Length')->numeric(),
                ]),
        ]);
    }

    /**
     * The list/create/edit/view pages plus a custom 'stats' page (slice 1.6)
     * — one auto-registered route per entry. The list slot uses the
     * generated-style Pages/ListPosts class (slice 1.10), which carries the
     * default CreateAction header action and a header widget strip.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            ...parent::getPages(),
            'index' => ListPosts::route('/'),
            'stats' => PostStats::route('/stats'),
            // The record-scoped manage page (the record-pages slice) — served
            // at /refilament/posts/{record}/manage, an infolist over the URL
            // record with Edit/Delete header actions.
            'manage' => PostManagePage::route('/{record}/manage'),
        ];
    }

    /**
     * The comments relation manager (slice 1.8) — one nested table under each
     * post, scoped by the typed relation endpoint.
     *
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }
}
