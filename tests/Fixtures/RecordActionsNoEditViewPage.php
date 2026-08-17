<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Actions\EditAction;
use Refilament\Refilament\Resources\Pages\ViewRecord;

/**
 * The view page of the RecordActionsNoEditResource — its EditAction cannot
 * resolve (the resource registers no edit page), so it must be dropped.
 */
class RecordActionsNoEditViewPage extends ViewRecord
{
    /**
     * @return array<int, EditAction>
     */
    protected static function getHeaderActions(string $resource): array
    {
        return [
            EditAction::make(),
        ];
    }
}
