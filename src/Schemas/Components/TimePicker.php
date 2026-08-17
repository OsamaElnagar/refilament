<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Time-only picker. Renders the same React date-time picker node as
 * `DateTimePicker`, but with the date portion disabled (state format
 * `H:i` or `H:i:s`).
 */
class TimePicker extends DateTimePicker
{
    public function hasDate(): bool
    {
        return false;
    }
}
