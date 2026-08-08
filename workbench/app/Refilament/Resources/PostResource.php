<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources;

use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Resources\Pages\PageRegistration;
use Refilament\Refilament\Resources\RelationManagers\RelationManager;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Action;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
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
        ]);
    }

    /**
     * The built-in list/create/edit/view pages plus a custom 'stats' page
     * (slice 1.6) — one auto-registered route per entry.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            ...parent::getPages(),
            'stats' => PostStats::route('/stats'),
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
