<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Resources;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Clusters\AccountCluster;

class UserResource extends Resource
{
    /**
     * The Account cluster (the page-clusters slice) — groups the users
     * resource under the cluster's sidebar entry and adds the Account crumb
     * to its pages' breadcrumbs. Its URLs stay at /refilament/users (the
     * resource route shape is shared; the cluster is a nav + breadcrumb
     * container here).
     *
     * @var class-string<AccountCluster>
     */
    protected static ?string $cluster = AccountCluster::class;

    /**
     * The Eloquent model this resource manages.
     *
     * @var class-string
     */
    protected static ?string $model = User::class;

    // Serve this resource under the demo's plural-id convention (the
    // generated defaults would be the singular 'user' / 'user-form').
    protected static ?string $tableId = 'users';

    protected static ?string $formId = 'user-form';

    public static function table(Table $table): Table
    {
        return $table
            ->id(static::getTableId())
            // No table-level header actions: the list page's default
            // page-header CreateAction owns the create button (slice 1.10 —
            // it navigates to /users/create).
            ->columns([
                Column::make('id')->label('ID')->sortable(),
                Column::make('name')->label('Name'),
                Column::make('email')->label('Email'),
                Column::make('email_verified_at')->label('Email Verified At')->sortable(),
                Column::make('password')->label('Password'),
            ])
            // Row delete (slice 1.2) — confirmed through the AlertDialog
            // primitive; users edit is deferred (the password field would
            // pre-fill/validate awkwardly until `dehydrated()`-style support
            // lands with the 1.7 record pages).
            ->actions([
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->successMessage('User deleted.')
                    ->action(static fn (User $record): mixed => $record->delete()),
            ])
            ->query(User::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->id(static::getFormId())
            ->components([
                TextInput::make('name')->label('Name')->required(),
                TextInput::make('email')->label('Email')->required(),
                TextInput::make('password')
                    ->label('Password')
                    ->required()
                    ->password()
                    ->revealable(),
            ])
            // The model's `hashed` cast hashes the password on save.
            ->submitUsing(static function (array $data): void {
                User::create($data);
            })
            ->successMessage('User created.');
    }
}
