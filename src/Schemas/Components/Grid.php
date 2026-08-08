<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Grid layout (slice 5).
 *
 * Arranges its children into `columns` equal-width columns. Children can span
 * multiple columns via `->columnSpan()` on the base component. Deferred:
 * responsive column definitions (`['md' => 2, 'lg' => 4]`), grid-level
 * column spanning, default column span.
 */
class Grid extends Layout
{
    protected int $columns = 2;

    public function getType(): string
    {
        return 'grid';
    }

    public function columns(int $columns): static
    {
        $this->columns = max($columns, 1);

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function toArray(?string $operation = null): array
    {
        return [
            'type' => $this->getType(),
            'columns' => $this->getColumns(),
            'schema' => $this->serializeChildren($operation),
        ];
    }
}
