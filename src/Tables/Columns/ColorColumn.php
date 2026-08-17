<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Refilament\Refilament\Tables\Column;

/**
 * Color column — renders the cell value as one or more color swatches, each a
 * small square filled with the raw state value (mirrors
 * `Filament\Tables\Columns\ColorColumn`, which is a swatch, not colored text).
 * The state is a CSS color string (or an array of them), serialized per record
 * as `{ value, colors }`; `copyable()` makes a swatch copy its color to the
 * clipboard on click.
 *
 * @method static \Refilament\Refilament\Tables\Columns\ColorColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\ColorColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\ColorColumn toggleable(bool $condition = true)
 */
class ColorColumn extends Column
{
    protected bool $isCopyable = false;

    public function copyable(bool $condition = true): static
    {
        $this->isCopyable = $condition;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->isCopyable;
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = 'color';

        if ($this->isCopyable()) {
            $payload['copyable'] = true;
        }

        return $payload;
    }

    public function serializeCell(mixed $record): mixed
    {
        $state = $this->getStateFor($record);

        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        $colors = is_array($state)
            ? array_values(array_filter($state, static fn (mixed $color): bool => $color !== null && $color !== ''))
            : [(string) $state];

        if ($colors === []) {
            return null;
        }

        return ['value' => implode(', ', $colors), 'colors' => $colors];
    }
}
