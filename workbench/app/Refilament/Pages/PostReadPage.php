<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Pages;

use Illuminate\Database\Eloquent\Model;
use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

/**
 * A standalone panel page hosting a read-only infolist (the page-infolists
 * slice) — a page whose payload IS an infolist document. The page declares
 * `infolist()` (entries) and overrides getInfolistRecord() to say which
 * record to read; everything else is wired by the package — the payload
 * serializes through serializePageInfolist(), and the generic
 * refilament/page-infolist component renders it, so this class is the whole
 * feature. Served at /refilament/latest-post by the shared
 * PanelPageController.
 */
class PostReadPage extends Page
{
    protected static ?string $navigationLabel = 'Latest post';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 97;

    protected static bool $shouldRegisterNavigation = true;

    public static function getSlug(): string
    {
        return 'latest-post';
    }

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-infolist';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Title'),
            TextEntry::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'draft' => 'secondary',
                    'archived' => 'warning',
                    default => 'secondary',
                }),
            TextEntry::make('author')->label('Byline'),
            TextEntry::make('views')->label('Views')->numeric(),
            TextEntry::make('published_at')->label('Published')->date()->placeholder('—'),
        ]);
    }

    /**
     * The record this standalone page reads — the latest published post, the
     * standalone analogue of a record page's URL record.
     */
    protected static function getInfolistRecord(): ?Model
    {
        return Post::query()->latest('published_at')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [
            'description' => 'A page hosting a read-only infolist — entries resolve their values from the latest post server-side.',
        ];
    }
}
