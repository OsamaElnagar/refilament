<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Refilament\Refilament\Tables\Column;

/**
 * Checkbox column — an inline-editable boolean rendered as a checkbox
 * (mirrors `Filament\Tables\Columns\CheckboxColumn`). Toggling it posts the
 * new value through the record-column update endpoint (a stateless
 * request/response; there is no Livewire component). The cell value is the
 * record's boolean attribute; `onColor()` / `offColor()` tint the control.
 *
 * @method static \Refilament\Refilament\Tables\Columns\CheckboxColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\CheckboxColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\CheckboxColumn toggleable(bool $condition = true)
 */
class CheckboxColumn extends Column
{
    protected ?string $onColor = null;

    protected ?string $offColor = null;

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

    public function offColor(?string $color): static
    {
        $this->offColor = $color;

        return $this;
    }

    public function getOffColor(): ?string
    {
        return $this->offColor;
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = 'checkbox';

        if ($this->onColor !== null) {
            $payload['onColor'] = $this->onColor;
        }

        if ($this->offColor !== null) {
            $payload['offColor'] = $this->offColor;
        }

        return $payload;
    }

    public function serializeCell(mixed $record): mixed
    {
        return ['value' => (bool) $this->getStateFor($record)];
    }
}
