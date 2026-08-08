<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Radio group field (slice 1.5).
 *
 * Mirrors Filament's Radio config surface where it is pure data. Options use
 * the shared Component option map — the same serialization Select uses — so
 * the contract shape is identical. `inline()` lays the options out beside
 * the label, `columns()` arranges them in a grid (clamped to 1-6, the
 * renderer's supported domain).
 *
 * Deferred for v1: option descriptions, per-option disabled states,
 * `boolean()` (yes/no radio), grid direction.
 */
class Radio extends Component
{
    protected bool $isInline = false;

    protected ?int $columns = null;

    public function getType(): string
    {
        return 'radio';
    }

    public function inline(bool $condition = true): static
    {
        $this->isInline = $condition;

        return $this;
    }

    public function isInline(): bool
    {
        return $this->isInline;
    }

    /**
     * Arrange the options in a grid of equal-width columns (1-6, Tailwind's
     * supported domain for radio grids). Clamped so the serialized value
     * always matches what the renderer can express.
     */
    public function columns(int $columns): static
    {
        $this->columns = min(max($columns, 1), 6);

        return $this;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'inline' => $this->isInline() ? true : null,
            'columns' => $this->columns,
        ]);
    }
}
