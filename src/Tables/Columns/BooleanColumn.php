<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

/**
 * Boolean column — displays a boolean value as a state-driven icon.
 *
 * @deprecated Use `IconColumn` with the `boolean()` method instead.
 */
class BooleanColumn extends IconColumn
{
    public function isBoolean(): bool
    {
        return true;
    }
}
