<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Actions\DeleteAction;
use Refilament\Refilament\Actions\EditAction;
use Refilament\Refilament\Actions\ViewAction;
use Refilament\Refilament\Resources\Pages\ViewRecord;

/**
 * The view page of the RecordActionsResource — a record page whose header
 * mixes the built-in navigation actions (EditAction / ViewAction resolve
 * their per-record page URLs) with a DeleteAction.
 */
class RecordActionsViewPage extends ViewRecord
{
    /**
     * @return array<int, EditAction|ViewAction|DeleteAction>
     */
    protected static function getHeaderActions(string $resource): array
    {
        return [
            EditAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
