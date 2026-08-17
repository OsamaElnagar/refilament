<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Closure;
use Refilament\Refilament\Tables\Column;

/**
 * Icon column — displays an icon with optional color. In boolean mode
 * (mirrors Filament v5, where `BooleanColumn` is deprecated in its favour)
 * the icon and colour resolve from the record's state: truthy values get a
 * check icon (success by default), falsy values an x icon (danger by
 * default), and the cell's text is the state's Yes/No label.
 *
 * Mirrors `Filament\Tables\Columns\IconColumn`.
 *
 * @method static \Refilament\Refilament\Tables\Columns\IconColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\IconColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\IconColumn toggleable(bool $condition = true)
 */
class IconColumn extends Column
{
    protected bool|Closure $isBoolean = false;

    protected ?string $trueIcon = null;

    protected ?string $trueColor = null;

    protected ?string $falseIcon = null;

    protected ?string $falseColor = null;

    /**
     * Enable boolean mode: the icon and colour resolve from the record's
     * state (mirrors Filament's `IconColumn::boolean()`).
     */
    public function boolean(bool|Closure $condition = true): static
    {
        $this->isBoolean = $condition;

        return $this;
    }

    public function isBoolean(): bool
    {
        return (bool) $this->evaluate($this->isBoolean);
    }

    /**
     * Configure the true state's icon and colour in one call (mirrors
     * Filament's `true()`).
     */
    public function true(?string $icon = null, ?string $color = null): static
    {
        return $this->trueIcon($icon)->trueColor($color);
    }

    /**
     * Configure the false state's icon and colour in one call (mirrors
     * Filament's `false()`).
     */
    public function false(?string $icon = null, ?string $color = null): static
    {
        return $this->falseIcon($icon)->falseColor($color);
    }

    public function trueIcon(?string $icon): static
    {
        $this->boolean();

        $this->trueIcon = $icon;

        return $this;
    }

    public function trueColor(?string $color): static
    {
        $this->boolean();

        $this->trueColor = $color;

        return $this;
    }

    public function falseIcon(?string $icon): static
    {
        $this->boolean();

        $this->falseIcon = $icon;

        return $this;
    }

    public function falseColor(?string $color): static
    {
        $this->boolean();

        $this->falseColor = $color;

        return $this;
    }

    public function serializeCell(mixed $record): mixed
    {
        $value = $this->getStateFor($record);

        if ($this->isBoolean()) {
            if ($value === null || $value === '') {
                return null;
            }

            $truthy = (bool) $value;

            return [
                'value' => $truthy ? 'Yes' : 'No',
                'icon' => $truthy ? ($this->trueIcon ?? 'check-circle') : ($this->falseIcon ?? 'x-circle'),
                'iconColor' => $truthy ? ($this->trueColor ?? 'success') : ($this->falseColor ?? 'danger'),
            ];
        }

        $icon = $this->resolveIconFor($record);

        if ($icon === null) {
            return (string) $value;
        }

        $cell = ['value' => (string) $value, 'icon' => $icon];

        $iconColor = $this->resolveIconColorFor($record);

        if ($iconColor !== null) {
            $cell['iconColor'] = $iconColor;
        }

        return $cell;
    }
}
