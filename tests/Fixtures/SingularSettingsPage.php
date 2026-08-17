<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

/**
 * A fixture singular-resource page (the singular-resource slice) — a
 * standalone page bound to ONE record of the workbench Post model, mirroring
 * Filament's documented pattern: the form loads the first record, auto-creates
 * it on the first save, and updates it afterwards. The slug field's unique
 * rule must never reject the record's own value (the machinery ignores it).
 */
class SingularSettingsPage extends Page
{
    protected static bool $shouldRegisterNavigation = true;

    protected static bool $hasUnsavedDataChangesAlert = true;

    /** @var class-string */
    protected static ?string $model = Post::class;

    public static function getSlug(): string
    {
        return 'singular-settings';
    }

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

                TextInput::make('author')
                    ->label('Author')
                    ->required()
                    ->rules(['required', 'string', 'max:255']),

                TextInput::make('status')
                    ->label('Status')
                    ->default('draft')
                    ->rules(['required', 'string']),

                TextInput::make('slug')
                    ->label('Slug')
                    ->rules(['required', 'string', 'unique:posts,slug']),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [
            'description' => 'A singular resource — one record, auto-created on first save.',
        ];
    }
}
