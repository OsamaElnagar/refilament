<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Force-delete bulk action (slice 2.2) for soft-deletable models.
 *
 * Mirrors Filament's ForceDeleteBulkAction without the Livewire-coupled
 * notifications/authorization: it fills in the label, color, confirmation and
 * the per-record `forceDelete()` behavior, so a resource only has to add the
 * instance to its `toolbarActions()`. Like every bulk action it runs once
 * against the whole selected set through the typed bulk endpoint; the trash
 * filter should be active (showing trashed records) for it to be reachable.
 */
class ForceDeleteBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'forceDelete';
    }

    public static function make(?string $name = null): static
    {
        $action = parent::make($name);

        $action->label('refilament::actions.force-delete.multiple.label')
            ->translateLabel();
        $action->color('danger');
        $action->requiresConfirmation();
        $action->successMessage('Selected posts permanently deleted.');
        $action->action(static function (Collection $records): void {
            $records->each(static fn (Model $record): mixed => $record->forceDelete());
        });

        return $action;
    }
}
