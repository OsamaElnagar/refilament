<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Actions\DeleteAction;
use Refilament\Refilament\Resources\Pages\EditRecord;

/**
 * The edit page of the RecordActionsResource — a record page whose header
 * carries a DeleteAction, the server action the record-scoped page-action
 * slice serializes with an endpoint + list-page redirect.
 */
class RecordActionsEditPage extends EditRecord
{
    /**
     * @return array<int, DeleteAction>
     */
    protected static function getHeaderActions(string $resource): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
