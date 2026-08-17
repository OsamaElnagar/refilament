<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\BulkAction;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

/**
 * Fixture table for the slice 4.1 authorization tests. Every action gate is a
 * closure reading the static flag, so tests flip the gate without registering
 * (or leaking) global Gate policies for the workbench models.
 */
class ProtectedPostsTable
{
    /** Whether the fixture actions are currently authorized. */
    public static bool $allow = false;

    public static function configure(Table $table): Table
    {
        return $table
            ->id('protected-posts')
            ->heading('Protected posts')
            ->query(Post::query())
            ->columns([
                Column::make('id')->label('ID'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('New protected post')
                    ->type('create')
                    ->schema('post-form')
                    ->authorize(static fn (): bool => static::$allow),
            ])
            ->actions([
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->authorize(static fn (Post $record): bool => static::$allow)
                    ->action(static fn (Post $record): mixed => $record->delete()),
            ])
            ->toolbarActions([
                BulkAction::make('wipe')
                    ->label('Wipe selected')
                    ->color('danger')
                    ->authorize(static fn (): bool => static::$allow)
                    ->action(static function ($records): void {
                        $records->each(static fn ($record): mixed => $record->delete());
                    }),
            ]);
    }
}
