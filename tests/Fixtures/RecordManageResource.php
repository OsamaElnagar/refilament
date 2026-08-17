<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * A fixture resource whose pages map includes two record-scoped custom pages
 * (the record-pages slice) — RecordManagePage (a `/{record}/manage` infolist
 * host) and RecordSettingsPage (a `/{record}/settings` form host). The URL
 * record drives both: the infolist entries resolve against it, the form
 * pre-fills from it and submits through the record-bound submit endpoint.
 */
class RecordManageResource extends Resource
{
    /** @var class-string */
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'record-manage';

    public static function table(Table $table): Table
    {
        return $table->id(static::getTableId())->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('record-manage-form');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'manage' => RecordManagePage::route('/{record}/manage'),
            'settings' => RecordSettingsPage::route('/{record}/settings'),
        ];
    }
}
