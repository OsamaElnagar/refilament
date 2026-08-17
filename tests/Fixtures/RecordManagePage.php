<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\Page;
use Refilament\Refilament\Schemas\Schema;

/**
 * A record-scoped custom page hosting an infolist (the record-pages slice) —
 * the `/{record}/manage` idiom. Entries resolve their values from the URL
 * record the payload binds; the page also forwards the record key.
 */
class RecordManagePage extends Page
{
    /** @var class-string<RecordManageResource>|null */
    protected static ?string $resource = RecordManageResource::class;

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
                    default => 'secondary',
                }),
            TextEntry::make('author')->label('Byline'),
            TextEntry::make('views')->label('Views')->numeric(),
        ]);
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        $model = static::resolveRecord($resource, (string) $record);

        return [
            'record' => $model->getKey(),
            ...parent::getPayload($resource, $refilament, $record),
        ];
    }
}
