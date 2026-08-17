<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Refilament\Refilament\Tables\Column;

/**
 * Toggle column — an inline-editable boolean rendered as a switch (mirrors
 * `Filament\Tables\Columns\ToggleColumn`). Toggling it posts the new value
 * through the record-column update endpoint (a stateless request/response).
 * `onColor()` tints the active switch; `onIcon()` / `offIcon()` label it.
 *
 * @method static \Refilament\Refilament\Tables\Columns\ToggleColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\ToggleColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\ToggleColumn toggleable(bool $condition = true)
 */
class ToggleColumn extends Column
{
    protected ?string $onColor = null;

    protected ?string $onIcon = null;

    protected ?string $offIcon = null;

    public function configure(): static
    {
        return $this->editable();
    }

    public function onColor(?string $color): static
    {
        $this->onColor = $color;

        return $this;
    }

    public function getOnColor(): ?string
    {
        return $this->onColor;
    }

    public function onIcon(?string $icon): static
    {
        $this->onIcon = $icon;

        return $this;
    }

    public function getOnIcon(): ?string
    {
        return $this->onIcon;
    }

    public function offIcon(?string $icon): static
    {
        $this->offIcon = $icon;

        return $this;
    }

    public function getOffIcon(): ?string
    {
        return $this->offIcon;
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = 'toggle';

        if ($this->onColor !== null) {
            $payload['onColor'] = $this->onColor;
        }

        if ($this->onIcon !== null) {
            $payload['onIcon'] = $this->onIcon;
        }

        if ($this->offIcon !== null) {
            $payload['offIcon'] = $this->offIcon;
        }

        return $payload;
    }

    public function serializeCell(mixed $record): mixed
    {
        return ['value' => (bool) $this->getStateFor($record)];
    }
}
