<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Schemas;

use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

/**
 * The posts form schema, extracted into its own class so any component can
 * compose it — the resource delegates to it today, a relation manager's
 * create/edit modal reuses it later. The Ahram production pattern
 * (`AccountForm::configure($schema)`): a plain static factory, not a
 * subclass, called from `Resource::form()` (docs/ARCHITECTURE.md,
 * "Relation managers & reusable table/form classes").
 *
 * The serialized `id` below addresses the submit / resolve-options
 * endpoints, so it must always match `PostResource::$formId`.
 */
class PostsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->id('post-form')
            ->components([
                Section::make()
                    ->heading('Details')
                    ->description('Every field is validated server-side on submit')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            TextInput::make('title')
                                ->label('Title')
                                ->placeholder('My post title')
                                ->helperText('At least 3 characters')
                                ->validation(['required', 'string', 'min:3', 'max:255'])
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(2),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->placeholder('my-post-title')
                                ->helperText('Lowercase letters, numbers and dashes; must be unique')
                                ->validation(['required', 'string', 'regex:/^[a-z0-9-]+$/', 'max:255', 'unique:posts,slug'])
                                ->required(),

                            TextInput::make('author')
                                ->label('Author')
                                ->placeholder('Ada Lovelace')
                                ->validation(['required', 'string', 'max:100'])
                                ->required(),
                        ]),

                        Select::make('status')
                            ->label('Status')
                            ->placeholder('Pick a status')
                            ->default('draft')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->validation(['required', 'in:draft,published,archived'])
                            ->required(),
                    ]),
            ])
            ->submitUsing(static function (array $data): void {
                Post::create($data);
            })
            ->successMessage('Post created.')
            // The typed record update endpoint (slice 1.7) persists through
            // this handler — the explicit update counterpart to submitUsing().
            ->updateUsing(static function (Post $record, array $data): mixed {
                return $record->update($data);
            })
            ->updateSuccessMessage('Post updated.')
            // A rich toast (slice 3.4): when a success notification is set it
            // precedes the plain message and renders as a titled sonner toast,
            // here a success variant with the created post as the body line.
            ->successNotification(
                Notification::make()
                    ->title('Post created.')
                    ->body('Your post is now live in the listing.')
                    ->success(),
            )
            ->updateSuccessNotification(
                Notification::make()
                    ->title('Post updated.')
                    ->body('The changes have been saved.')
                    ->success(),
            );
    }
}
