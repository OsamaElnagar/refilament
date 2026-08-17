<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use Illuminate\Database\Eloquent\Model;

/**
 * Built-in row delete action (professional actions slice — docs/ROADMAP.md
 * "2.5 Table & bulk actions"). Mirrors Filament's DeleteAction defaults so a
 * consumer writes `DeleteAction::make()` and gets the standard behavior:
 * a trash icon, danger color, confirmation prompt and `$record->delete()`
 * (with the model's own `deleting` events / soft-delete cascades honored).
 *
 * The defaults live in setUp(), which runs after the global configureUsing()
 * pipeline and before the consumer's own fluent calls — so `->label()`,
 * `->successMessage()`, a custom `->action()` closure etc. all win over them,
 * exactly like Filament.
 */
class DeleteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'delete';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Delete');
        $this->icon('trash');
        $this->color('danger');
        $this->requiresConfirmation();
        $this->modalHeading('Delete record');
        $this->modalDescription('This action cannot be undone.');
        $this->successMessage('Record deleted.');
        $this->authorize('delete');
        $this->action(static fn (Model $record): mixed => $record->delete());
    }
}
