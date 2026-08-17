<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A globally-searchable fixture resource whose per-record result actions are
 * server closures (slice 3.5) — the typed global-search action endpoint
 * rebuilds them from getGlobalSearchResultActions() and calls the closure.
 */
class SearchActionResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    public static function table(Table $table): Table
    {
        return $table
            ->id(static::getTableId())
            ->columns([
                Column::make('id')->label('ID'),
                Column::make('title')->label('Title')->searchable(),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * A distinct search category label — the fixture shares the Post model
     * with the workbench PostResource, and categories are keyed by the plural
     * model label (duplicate labels throw).
     */
    public static function getPluralModelLabel(): string
    {
        return 'Search posts';
    }

    /**
     * Three closure actions: `pin` always visible, `unpublish` visible only
     * for published records (exercising the per-record visibility gate), and
     * `delete` flagged `requiresConfirmation` so the client pauses at a
     * confirm dialog before the request is sent.
     *
     * @return array<int, Action>
     */
    public static function getGlobalSearchResultActions(mixed $record): array
    {
        return [
            // The icon/tooltip pair (slice 3.5) serializes onto the hit's
            // actions — the search dialog renders the named icon through the
            // shared lucide registry and wraps the button in a hover tooltip.
            Action::make('pin')
                ->label('Pin')
                ->icon('pin')
                ->tooltip('Pin this post')
                ->action(static fn (Post $post): mixed => $post->update(['status' => 'published']))
                ->successMessage('Post pinned.'),
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon('eye-off')
                ->tooltip('Unpublish this post')
                ->visible(static fn (Post $post): bool => $post->status === 'published')
                ->action(static fn (Post $post): mixed => $post->update(['status' => 'draft']))
                ->successMessage('Post unpublished.'),
            Action::make('delete')
                ->label('Delete')
                ->color('danger')
                ->icon('trash')
                ->tooltip('Delete this post')
                ->requiresConfirmation()
                ->action(static fn (Post $post): mixed => $post->delete())
                ->successMessage('Post deleted.'),
        ];
    }
}
