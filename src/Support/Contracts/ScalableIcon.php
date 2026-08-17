<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Contracts;

use Refilament\Refilament\Support\Enums\IconSize;

/**
 * Contract implemented by icon-catalog enums (mirrors Filament's
 * `ScalableIcon`). Implementations resolve an icon to the canonical key the
 * React renderer's icon map understands, given a desired size.
 *
 * In our Inertia + React stack the canonical key is size-independent — icon
 * sizing is a CSS `className` concern on the renderer — but the size argument
 * is kept so the API mirrors Filament's and stays forward-compatible with any
 * future size-variant catalog (e.g. real heroicons).
 */
interface ScalableIcon
{
    public function getIconForSize(IconSize $size): string;
}
