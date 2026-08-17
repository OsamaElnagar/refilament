<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Schemas;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Schemas\Components\Grid;
use Refilament\Refilament\Schemas\Components\Section;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Enums\PostStatus;
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
                                ->rules(['required', 'string', 'min:3', 'max:255'])
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(2),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->placeholder('my-post-title')
                                ->helperText('Lowercase letters, numbers and dashes; must be unique')
                                // Operation-aware disable (slice C6): the slug
                                // is typed on create but locked on edit — the
                                // immutable identifier pattern.
                                ->disabledOn('edit')
                                ->rules(['required', 'string', 'regex:/^[a-z0-9-]+$/', 'max:255', 'unique:posts,slug'])
                                ->required(),

                            TextInput::make('author')
                                ->label('Author')
                                ->placeholder('Ada Lovelace')
                                ->rules(['required', 'string', 'max:100'])
                                ->required()
                                // Hint actions (slice C5): a small action in
                                // the label row, mirroring the Ahram
                                // "View client" idiom. `visibleWhenFilled` is
                                // a pure client-side rule (no round trip) —
                                // the button appears once the author is typed
                                // and opens the users page in a new tab.
                                ->hintActions([
                                    Action::make('view-authors')
                                        ->label('View authors')
                                        ->icon('document')
                                        ->tooltip('Open the users page in a new tab')
                                        ->url('/refilament/users')
                                        ->openUrlInNewTab()
                                        ->visibleWhenFilled('author'),
                                ]),
                        ]),

                        Select::make('user_id')
                            ->label('User')
                            ->placeholder('Choose an author')
                            // Relationship options (slice C1): the option list
                            // is resolved server-side from Post's `user`
                            // relationship — the Ahram
                            // `->relationship('account', 'name')` idiom. The
                            // shipped list is searchable client-side.
                            ->relationship('user', 'name')
                            ->model(Post::class)
                            ->searchable()
                            // Hint text (slice C5): the label-row hint slot
                            // — Filament's `hint()`, serialized as data.
                            ->hint('Searchable user list'),

                        Select::make('status')
                            ->label('Status')
                            ->placeholder('Pick a status')
                            ->default('draft')
                            // Enum options (slice C2): the option list is
                            // derived from PostStatus's cases — the Ahram
                            // `->options(SomeEnum::class)` idiom.
                            ->options(PostStatus::class)
                            ->rules(['required', 'in:draft,published,archived'])
                            ->required()
                            // Hint icon (slice C5): Filament's `hintIcon()` —
                            // a small glyph with a hover tooltip in the
                            // label row.
                            ->hintIcon('chart-bar', 'Shown as a badge in the listing'),

                        TextInput::make('created_at')
                            ->label('Created')
                            ->placeholder('—')
                            // Slice C4 + C6 together, the Ahram computed-field
                            // idiom: hidden while creating (the server stamps
                            // it), and on edit it renders read-only and is
                            // never submitted (`dehydrated(false)`) so the
                            // stored timestamp can't be overwritten by the form.
                            ->readOnly()
                            ->dehydrated(false)
                            ->hiddenOn('create'),
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
