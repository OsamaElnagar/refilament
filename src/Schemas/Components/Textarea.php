<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Multi-line text field (slice 1.4).
 *
 * Mirrors Filament's Textarea config API where it is pure data. The shared
 * Component base supplies label, placeholder, helperText, required, maxLength,
 * validation and columnSpan; `rows` is serialized only when set (omission
 * convention) and the client defaults to 3 rows.
 *
 * Deferred for v1: `cols` (the vendored Textarea primitive is full-width),
 * `autosize` (the primitive grows with content already), minLength, state
 * casts.
 */
class Textarea extends Component
{
    protected ?int $rows = null;

    public function getType(): string
    {
        return 'textarea';
    }

    public function rows(int $rows): static
    {
        $this->rows = max($rows, 1);

        return $this;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function toArray(): array
    {
        return $this->filterNullValues([
            ...parent::toArray(),
            'rows' => $this->rows,
        ]);
    }
}
