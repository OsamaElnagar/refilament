<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Date-only picker. Renders the same React date-time picker node as
 * `DateTimePicker`, but with the time portion disabled (state format
 * `Y-m-d`).
 */
class DatePicker extends DateTimePicker
{
    public function hasTime(): bool
    {
        return false;
    }
}
