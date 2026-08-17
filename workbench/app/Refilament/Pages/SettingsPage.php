<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;

/**
 * A standalone panel page hosting a form (the page-forms slice) — the
 * canonical settings-page idiom, and the seed of the singular-resource
 * pattern: one form bound to one record (here, the authenticated user).
 *
 * The page declares `form()` (the schema, its server-side submit handler)
 * and `getFormData()` (the record's current values); everything else is
 * wired by the package — the schema resolver registers under getFormId(),
 * the payload serializes through serializePageForm(), and the generic
 * refilament/page-form component renders it, so this class is the whole
 * feature. Served at /refilament/settings by the shared PanelPageController.
 */
class SettingsPage extends Page
{
    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected static bool $shouldRegisterNavigation = true;

    /**
     * The browser prompts before navigating away while the form is dirty.
     */
    protected static bool $hasUnsavedDataChangesAlert = true;

    public static function getSlug(): string
    {
        return 'settings';
    }

    public static function getInertiaComponent(): string
    {
        return 'refilament/page-form';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading('Profile')
                    ->description('Your name and email — saved to your account')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->rules(['required', 'string', 'max:255'])
                                ->columnSpan(1),

                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->rules(['required', 'email', 'max:255'])
                                ->columnSpan(1),
                        ]),
                    ]),
            ])
            ->successMessage('Settings saved.')
            ->submitUsing(function (array $data): void {
                $user = Auth::user();

                if ($user !== null) {
                    $user->update($data);
                }
            });
    }

    /**
     * The form's starting values — the authenticated user's record, filled
     * like Filament's `mount()` would (the singular-resource idiom). The
     * `$record` parameter is never used here (the page resolves the user
     * itself); it exists to satisfy the base signature.
     *
     * @return array<string, mixed>
     */
    public static function getFormData(?Model $record = null): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        return [
            /** @phpstan-ignore-next-line Access to Eloquent model attribute */
            'name' => $user->name,
            /** @phpstan-ignore-next-line Access to Eloquent model attribute */
            'email' => $user->email,
        ];
    }

    public static function getFormSubmitLabel(): string
    {
        return 'Save settings';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [
            'description' => 'A page form — state is client-held, validated server-side on submit.',
        ];
    }
}
