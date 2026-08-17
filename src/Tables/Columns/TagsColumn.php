<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Refilament\Refilament\Tables\Column;

/**
 * Tags column — renders the cell value as a set of small badges (Filament's
 * `TagsColumn`; in v5 it's a deprecated `TextColumn->badge()` subclass, so the
 * real machinery is "array state rendered as a badge list" — we ship exactly
 * that as its own column kind).
 *
 * The state is an array of strings (or a single string), serialized per record
 * as `{ value, tags, remaining? }`; `limitList()` caps the number of badges and
 * reports how many were left off.
 *
 * @method static \Refilament\Refilament\Tables\Columns\TagsColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\TagsColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\TagsColumn toggleable(bool $condition = true)
 */
class TagsColumn extends Column
{
    protected ?int $listLimit = null;

    /**
     * Cap the number of rendered badges; the overflow count ships as
     * `remaining` on the cell (mirrors Filament's `limitList()`).
     */
    public function limitList(?int $length = 3): static
    {
        $this->listLimit = $length;

        return $this;
    }

    public function getListLimit(): ?int
    {
        return $this->listLimit;
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = 'tags';

        if ($this->listLimit !== null) {
            $payload['limit'] = $this->listLimit;
        }

        return $payload;
    }

    public function serializeCell(mixed $record): mixed
    {
        $state = $this->getStateFor($record);

        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        $tags = is_array($state)
            ? array_values(array_filter($state, static fn (mixed $tag): bool => $tag !== null && $tag !== ''))
            : [(string) $state];

        if ($tags === []) {
            return null;
        }

        $limit = $this->listLimit;
        $remaining = ($limit !== null && count($tags) > $limit) ? count($tags) - $limit : 0;

        if ($remaining > 0) {
            $tags = array_slice($tags, 0, $limit);
        }

        $cell = ['value' => implode(', ', $tags), 'tags' => $tags];

        if ($remaining > 0) {
            $cell['remaining'] = $remaining;
        }

        return $cell;
    }
}
