<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Built-in bulk delete action (professional actions slice — docs/ROADMAP.md
 * "2.5 Table & bulk actions"). Mirrors Filament's DeleteBulkAction defaults:
 * a consumer writes `DeleteBulkAction::make()` in toolbarActions() and gets
 * per-record policy authorization (each selected record checked against the
 * model's `delete` ability, the unauthorized filtered out), a confirmation
 * prompt and `->delete()` on every remaining record.
 */
class DeleteBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'delete';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Delete selected');
        $this->icon('trash');
        $this->color('danger');
        $this->requiresConfirmation();
        $this->modalHeading('Delete selected records');
        $this->modalDescription('The selected records will be permanently removed. This action cannot be undone.');
        $this->successMessage('Selected records deleted.');
        $this->authorize('delete');
        $this->authorizeIndividualRecords('delete');
        $this->action(static function (EloquentCollection $records): void {
            foreach ($records as $record) {
                $record->delete();
            }
        });
    }
}
