<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

/**
 * Icon entry — a boolean-ish value rendered as a single icon (slice 3.3).
 * When the state is truthy the entry shows the configured `->icon()` in its
 * `->iconColor()`; a false/empty state renders the placeholder. Mirrors
 * `Filament\Infolists\Components\IconEntry`.
 */
class IconEntry extends Entry
{
    public function getType(): string
    {
        return 'icon_entry';
    }
}
