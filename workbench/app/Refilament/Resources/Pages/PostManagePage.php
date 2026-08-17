<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources\Pages;

use Refilament\Refilament\Actions\DeleteAction;
use Refilament\Refilament\Actions\EditAction;
use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\Page;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Refilament\Resources\PostResource;

/**
 * A record-scoped custom page (the record-pages slice) — served at
 * /refilament/posts/{record}/manage. Reads the URL record through an
 * infolist (entries resolve against the record the serializer binds) and
 * carries Edit/Delete header actions, so it is the \"manage this post\"
 * surface: read the record, act on it — all through the generic
 * refilament/page-infolist component, zero consumer React code.
 */
final class PostManagePage extends Page
{
    /** @var class-string<PostResource> */
    protected static ?string $resource = PostResource::class;

    protected static string $routePath = '/{record}/manage';

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
                    'draft' => 'secondary',
                    'published' => 'success',
                    'archived' => 'warning',
                    default => 'secondary',
                }),
            TextEntry::make('author')->label('Byline'),
            TextEntry::make('views')->label('Views')->numeric(),
            TextEntry::make('published_at')->label('Published')->date()->placeholder('—'),
        ]);
    }

    /**
     * @return array<int, EditAction|DeleteAction>
     */
    protected static function getHeaderActions(string $resource): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        // Resolves the {record} segment through the page's record-binding
        // query and gates it with the resource's view policy — 404s when the
        // post is gone, 403s when the current user may not view it.
        $model = self::resolveRecord($resource, (string) $record);

        return [
            'record' => $model->getKey(),
            ...parent::getPayload($resource, $refilament, $record),
        ];
    }
}
