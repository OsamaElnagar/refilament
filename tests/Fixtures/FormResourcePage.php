<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Resources\Pages\Page;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;

/**
 * A custom resource page hosting a form (the page-forms slice) — the
 * resource-page analogue of the standalone settings page. Its form payload
 * rides on the page's getPayload() via serializePageForm(), and its schema
 * resolver registers under getFormId() so the typed submit endpoint works.
 */
class FormResourcePage extends Page
{
    /** @var class-string<FormPageResource>|null */
    protected static ?string $resource = FormPageResource::class;

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-form';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->rules(['required', 'string', 'max:255']),
            ])
            ->successMessage('Form submitted.')
            ->submitUsing(static function (array $data): void {
                // The fixture's form persists nothing — the round-trip test
                // only exercises the endpoint + success surface.
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getViewData(string $resource): array
    {
        return [
            'description' => 'A custom resource page hosting a form.',
        ];
    }
}
