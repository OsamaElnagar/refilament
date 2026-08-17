<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Closure;
use Illuminate\Support\Carbon;
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

    protected bool $shouldTranslateLabel = false;

    protected bool $collapsible = false;

    protected bool $isDate = false;

    /** @var Closure(mixed): mixed|null */
    protected ?Closure $getKeyFromRecordUsing = null;

    /** @var Closure(mixed): mixed|null */
    protected ?Closure $getTitleFromRecordUsing = null;

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
     * Treat the group label as a translation key resolved through the app's
     * translator when the group is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

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

    /**
     * Mark this group as date-backed. Rows then share a group key on their
     * date (`Y-m-d`) rather than the raw timestamp, and the header title is
     * rendered in a human date format. Mirrors Filament's `Group::date()`.
     */
    public function date(bool $condition = true): static
    {
        $this->isDate = $condition;

        return $this;
    }

    /**
     * Compute the group key from a record via a closure instead of reading
     * the column directly. Mirrors Filament's `Group::getKeyFromRecordUsing()`.
     * The closure receives the record and may return any value; the key is
     * still normalized (booleans to '1'/'0', dates to `Y-m-d`) afterwards.
     *
     * @param  Closure(mixed $record): mixed  $callback
     */
    public function getKeyFromRecordUsing(Closure $callback): static
    {
        $this->getKeyFromRecordUsing = $callback;

        return $this;
    }

    /**
     * Compute the header title from a record via a closure instead of falling
     * back to the raw value. Mirrors Filament's
     * `Group::getTitleFromRecordUsing()`. For a date group the returned value
     * is still parsed and rendered as a human date.
     *
     * @param  Closure(mixed $record): mixed  $callback
     */
    public function getTitleFromRecordUsing(Closure $callback): static
    {
        $this->getTitleFromRecordUsing = $callback;

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
        $label = $this->label ?? Str::headline(
            str_contains($this->column, '.') ? Str::afterLast($this->column, '.') : $this->column,
        );

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isDate(): bool
    {
        return $this->isDate;
    }

    /**
     * @return Closure(mixed $record): mixed|null
     */
    public function hasTitleFromRecordUsing(): ?Closure
    {
        return $this->getTitleFromRecordUsing;
    }

    /**
     * Resolve the group value for a record — the normalized value it is
     * grouped by. Mirrors Filament's `getKey()` / `getStringKey()`: a custom
     * `getKeyFromRecordUsing()` closure short-circuits the raw attribute, and
     * a date group collapses the value to its `Y-m-d` date string.
     */
    public function getKeyFor(mixed $record): string
    {
        $value = $this->getKeyFromRecordUsing instanceof Closure
            ? ($this->getKeyFromRecordUsing)($record)
            : data_get($record, $this->column);

        if ($value === null || $value === '') {
            return '';
        }

        if ($this->isDate) {
            return ($value instanceof Carbon ? $value : Carbon::parse($value))->toDateString();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * The display title for a record's group run. A custom
     * `getTitleFromRecordUsing()` closure wins; otherwise a date group
     * formats its value as a human date, and any other group falls back to
     * the raw value.
     */
    public function getTitleFor(mixed $record): string
    {
        if ($this->getTitleFromRecordUsing instanceof Closure) {
            $title = ($this->getTitleFromRecordUsing)($record);
        } else {
            $title = data_get($record, $this->column);
        }

        if ($title === null || $title === '') {
            return '';
        }

        if ($this->isDate) {
            return ($title instanceof Carbon ? $title : Carbon::parse($title))->translatedFormat('M j, Y');
        }

        if (is_bool($title)) {
            return $title ? '1' : '0';
        }

        return (string) $title;
    }
}
