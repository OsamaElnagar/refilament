<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\Page;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;

/**
 * A record-scoped custom page hosting a form (the record-pages slice) — a
 * `/{record}/settings` page that edits the URL record. The form pre-fills
 * from the record (the payload serializer binds it), and saving posts to the
 * record-bound submit endpoint — validated against the form's rules with the
 * unique rule ignoring the record — so the page never needs its own submit
 * handler to update the record it manages.
 */
class RecordSettingsPage extends Page
{
    /** @var class-string<RecordManageResource>|null */
    protected static ?string $resource = RecordManageResource::class;

    protected static bool $hasUnsavedDataChangesAlert = true;

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-form';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Title')
                ->required()
                ->rules(['required', 'string', 'max:255']),

            TextInput::make('author')
                ->label('Author')
                ->required()
                ->rules(['required', 'string', 'max:255']),

            TextInput::make('slug')
                ->label('Slug')
                ->rules(['required', 'string', 'unique:posts,slug']),

            TextInput::make('status')
                ->label('Status')
                ->rules(['required', 'string']),
        ]);
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        $model = static::resolveRecord($resource, (string) $record, 'edit');

        return [
            'record' => $model->getKey(),
            ...parent::getPayload($resource, $refilament, $record),
        ];
    }
}
