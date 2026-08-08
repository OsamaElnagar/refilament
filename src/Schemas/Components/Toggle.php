<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Boolean toggle field (slice 1.4).
 *
 * Like Checkbox, but rendered as a switch (the vendored Switch primitive).
 * The shared BooleanField base supplies the false default, the intrinsic
 * `boolean` rule, `accepted()` and `inline()`.
 *
 * Deferred for v1: on/off colors and icons, state casts.
 */
class Toggle extends BooleanField
{
    public function getType(): string
    {
        return 'toggle';
    }
}
