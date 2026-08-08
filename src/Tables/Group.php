<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Illuminate\Support\Str;

/**
 * Table record grouping (slice 2.3 — docs/ROADMAP.md).
 *
 * A grouping splits the table's records into runs by a shared column value,
 * with a header row before each run. Register groups on a table and pick the
 * active one either as the default (`->defaultGroup('status')`) or via the
 * table endpoint's validated `group` query param.
 *
 * Mirrors Filament's `Filament\Tables\Grouping\Group`. Grouping is a pure
 * data descriptor in Filament — the group *heading* derives from the group
 * column's cell formatters — so there is no reactivity to defer here. The
 * rows are annotated server-side (`groupKey` / `groupTitle`); the client
 * renders the header rows and collapse state (pure React state, never
 * persisted). Per-group footer subtotals reuse the summarizer machinery
 * (Table::serializeGroupSummary); date grouping is deferred.
 */
class Group
{
    protected ?string $label = null;

    protected bool $collapsible = false;

    final public function __construct(protected string $column) {}

    public static function make(string $column): static
    {
        return new static($column);
    }

    /**
     * The label shown above the group-header. Defaults to the column's
     * display name (a readable humanized heading, e.g. "Status").
     */
    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Allow the client to collapse this group's rows to just its header.
     * Pure client state — never persisted to the server.
     */
    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * The per-run heading. Defaults to the column's headline; the table
     * resolver may override it with the column's own label when present.
     */
    public function getLabel(): string
    {
        return $this->label ?? Str::headline(
            str_contains($this->column, '.') ? Str::afterLast($this->column, '.') : $this->column,
        );
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    /**
     * Resolve the group value for a record — the raw value it is grouped by.
     * Mirrors Filament's `getKey()`, simplified to the column's attribute
     * (custom `getKeyFromRecordUsing()` is deferred).
     */
    public function getKeyFor(mixed $record): string
    {
        $value = data_get($record, $this->column);

        return $value === null ? '' : (string) $value;
    }
}
