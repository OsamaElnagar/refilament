<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Restore bulk action (slice 2.2) for soft-deletable models.
 *
 * Mirrors Filament's RestoreBulkAction without the Livewire-coupled
 * notifications/authorization: it fills in the label, color, confirmation and
 * the per-record `restore()` behavior, so a resource only has to add the
 * instance to its `toolbarActions()`. Like every bulk action it runs once
 * against the whole selected set through the typed bulk endpoint; the trash
 * filter should be active (showing trashed records) for it to be reachable.
 */
class RestoreBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'restore';
    }

    public static function make(?string $name = null): static
    {
        $action = parent::make($name);

        $action->label('refilament::actions.restore.multiple.label')
            ->translateLabel();
        $action->color('secondary');
        $action->requiresConfirmation();
        $action->successMessage('Selected posts restored.');
        $action->action(static function (Collection $records): void {
            $records->each(static function (Model $record): void {
                if (! method_exists($record, 'restore')) {
                    return;
                }

                $record->restore();
            });
        });

        return $action;
    }
}
