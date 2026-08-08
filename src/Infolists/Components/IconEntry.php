<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

use Closure;

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

    /**
     * Register the icon shown when the state is truthy. Accepts a static key
     * or a closure resolving one from the record/state. Mirrors Filament's
     * `->boolean()`-style icon resolution.
     */
    public function icon(string|Closure $icon): static
    {
        return parent::icon($icon);
    }
}
