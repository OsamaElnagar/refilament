<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Boolean checkbox field (slice 1.4).
 *
 * The shared BooleanField base supplies the Filament-faithful config surface:
 * a false default, the intrinsic `boolean` validation rule, `accepted()` for
 * consent-required boxes and `inline()` for the beside-the-label layout. The
 * React runtime renders it with the vendored Checkbox primitive.
 *
 * Deferred for v1: indeterminate state, state casts.
 */
class Checkbox extends BooleanField
{
    public function getType(): string
    {
        return 'checkbox';
    }
}
