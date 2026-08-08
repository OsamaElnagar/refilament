<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use BackedEnum;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use LogicException;
use Refilament\Refilament\Tables\Summarizers\Summarizer;

/**
 * Table column (slice 6, extended 2.1).
 *
 * A read-only column mapped to a record attribute by `name`, or to a
 * computed/related value through a `getStateUsing()` closure. Dot-notation
 * names (`Column::make('parent.name')`) resolve the related attribute
 * server-side via data_get — no getStateUsing needed (Filament's native
 * relationship-column behavior).
 *
 * Value formatting is resolved server-side at serialization (formatStateUsing
 * / money / date / numeric / ...), so closures never survive the wire: the
 * shipped cell value is always the already-formatted string. Display kinds
 * (badge, icon, color, url) ship as a per-record structured cell
 * `{ value, badge?, color?, icon?, url? }` — plain columns keep a scalar
 * cell, display columns resolve their presentation per record (closures
 * included) so `->badge()->color(fn ($state) => ...)` works like Filament.
 */
class Column
{
    protected ?string $label = null;

    protected ?string $placeholder = null;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected bool $toggleable = false;

    /**
     * Server-side resolver for this column's raw cell state (e.g. a related
     * model attribute). Mirrors Filament's getStateUsing(); the closure never
     * survives serialization — the table resolver rebuilds it when rows are
     * served (docs/CONTRACT.md, "Tables").
     *
     * @var Closure(mixed): mixed|null
     */
    protected ?Closure $stateResolver = null;

    /**
     * Server-side value formatter (Slice 2.1). Receives the resolved state
     * and the record; returns the display value (usually a string). Never
     * serialized — evaluated on every row.
     *
     * @var Closure(mixed, mixed): mixed|null
     */
    protected ?Closure $formatUsing = null;

    protected string $prefix = '';

    protected string $suffix = '';

    /** @var string|array<int|string, string|Closure>|Closure|null */
    protected mixed $color = null;

    protected bool $isBadge = false;

    /** @var string|Closure|null */
    protected mixed $url = null;

    protected bool $openUrlInNewTab = false;

    /** @var string|Closure|null */
    protected mixed $icon = null;

    /** @var string|Closure|null */
    protected mixed $iconColor = null;

    /** @var array<int, Summarizer> */
    protected array $summarizers = [];

    final public function __construct(protected ?string $name = null) {}

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Mark the column as sortable. Clients send `sort` / `direction` query
     * params to the index endpoint, which orders the query server-side
     * (docs/CONTRACT.md, "Tables").
     */
    public function sortable(bool $condition = true): static
    {
        $this->assertDirectiveOnRelationship('sortable');

        $this->sortable = $condition;

        return $this;
    }

    /**
     * Mark the column as searchable: the global `search` query param matches
     * against it with a `LIKE` clause (docs/CONTRACT.md, "Tables").
     */
    public function searchable(bool $condition = true): static
    {
        $this->assertDirectiveOnRelationship('searchable');

        $this->searchable = $condition;

        return $this;
    }

    /**
     * Mark the column as toggleable: the client can hide or show it through
     * the column visibility menu. Applied client-side only — the server
     * always serves every column (docs/CONTRACT.md, "Tables").
     */
    public function toggleable(bool $condition = true): static
    {
        $this->toggleable = $condition;

        return $this;
    }

    /**
     * Register a closure that resolves this column's cell state from the
     * record — typically a related model attribute, e.g.
     * `fn (Post $record): ?string => $record->user?->name`. The closure
     * never survives serialization; the table resolver rebuilds it when rows
     * are served.
     *
     * @param  Closure(mixed $record): mixed  $resolver
     */
    public function getStateUsing(Closure $resolver): static
    {
        $this->stateResolver = $resolver;

        return $this;
    }

    /**
     * Register a server-side formatter for this column's state. The closure
     * receives the (already resolved) raw state and the record, and returns
     * the value displayed in the cell (usually a string). It never survives
     * serialization — it is evaluated on every row when rows are served.
     * Mirrors Filament's formatStateUsing().
     *
     * @param  Closure(mixed $state, mixed $record): mixed  $formatter
     */
    public function formatStateUsing(Closure $formatter): static
    {
        $this->formatUsing = $formatter;

        return $this;
    }

    /**
     * Format the state as a currency amount, e.g. "$1,234.56" (uses
     * Illuminate\Support\Number::currency). Mirrors Filament's money().
     */
    public function money(?string $currency = null, int $divideBy = 100): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($currency, $divideBy): ?string {
            if (blank($state) || ! is_numeric($state)) {
                return null;
            }

            $amount = (float) $state;

            if ($divideBy > 0) {
                $amount = $amount / $divideBy;
            }

            return (string) Number::currency($amount, $currency ?? 'USD');
        });
    }

    /**
     * Format the state as a date (Carbon::translatedFormat). A null format
     * falls back to a readable default.
     */
    public function date(?string $format = null, ?string $timezone = null): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($format, $timezone): ?string {
            if (blank($state)) {
                return null;
            }

            $date = $timezone === null
                ? Carbon::parse($state)
                : Carbon::parse($state)->setTimezone($timezone);

            return $date->translatedFormat($format ?? 'M j, Y');
        });
    }

    /**
     * Format the state as a date and time.
     */
    public function dateTime(?string $format = null, ?string $timezone = null): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($format, $timezone): ?string {
            if (blank($state)) {
                return null;
            }

            $date = $timezone === null
                ? Carbon::parse($state)
                : Carbon::parse($state)->setTimezone($timezone);

            return $date->translatedFormat($format ?? 'M j, Y H:i');
        });
    }

    /**
     * Format the state as a time only.
     */
    public function time(?string $format = null, ?string $timezone = null): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($format, $timezone): ?string {
            if (blank($state)) {
                return null;
            }

            $date = $timezone === null
                ? Carbon::parse($state)
                : Carbon::parse($state)->setTimezone($timezone);

            return $date->translatedFormat($format ?? 'H:i');
        });
    }

    /**
     * Format the state as a number with grouped thousands and (optionally)
     * fixed decimal places (Illuminate\Support\Number::format).
     */
    public function numeric(?int $decimalPlaces = null): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($decimalPlaces): ?string {
            if (blank($state) || ! is_numeric($state)) {
                return null;
            }

            return (string) Number::format((float) $state, $decimalPlaces);
        });
    }

    /**
     * Truncate the displayed value to a character limit (Filament's limit()).
     */
    public function limit(int $length = 100, string $end = '...'): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($length, $end): string {
            if ($state === null || $state === '') {
                return (string) $state;
            }

            return Str::limit((string) $state, $length, $end);
        });
    }

    /**
     * Prepend a static prefix to the displayed value.
     */
    public function prefix(string $prefix): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($prefix): string {
            return $prefix.(string) ($state ?? '');
        });
    }

    /**
     * Append a static suffix to the displayed value.
     */
    public function suffix(string $suffix): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($suffix): string {
            return (string) ($state ?? '').$suffix;
        });
    }

    /**
     * Render the value as a shadcn Badge. Combine with color() for the
     * badge's color (static or per-record).
     */
    public function badge(bool $condition = true): static
    {
        $this->isBadge = $condition;

        return $this;
    }

    /**
     * Color the value (text or badge). Accepts a static name, an array map,
     * or a per-record closure resolving the color from the state — the common
     * `->badge()->color(fn ($state) => ...)` idiom.
     *
     * @param  string|array<int|string, string|Closure>|Closure  $color
     */
    public function color(string|array|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * A state → color mapping: keys are the color for that exact state value,
     * with an optional catch-all numeric key as the default. Mirrors
     * Filament's colors().
     *
     * @param  array<int|string, string|Closure>  $colors
     */
    public function colors(array $colors): static
    {
        return $this->color(static function (mixed $state) use ($colors): ?string {
            foreach ($colors as $color => $condition) {
                if (is_numeric($color)) {
                    return $condition instanceof Closure ? (string) $condition($state) : (string) $condition;
                }

                if ($condition instanceof Closure) {
                    if ((bool) $condition($state)) {
                        return (string) $color;
                    }
                } elseif ($condition === $state) {
                    return (string) $color;
                }
            }

            return null;
        });
    }

    /**
     * Attach a footer summary to this column (slice 1.7) — e.g.
     * `->summarize(Sum::make()->label('Total views'))`. The aggregate is
     * computed server-side over the table's filtered query when the payload
     * is built; the summarizer never survives serialization (docs/CONTRACT.md,
     * "Tables"). Mirrors Filament's `->summarize()` and the Ahram report
     * pages.
     *
     * @param  Summarizer|array<int, Summarizer>  $summarizers
     */
    public function summarize(Summarizer|array $summarizers): static
    {
        foreach (is_array($summarizers) ? $summarizers : [$summarizers] as $summarizer) {
            // A standalone `Sum::make()` has no column yet — inherit the
            // column's name so `Sum::make()->label('Total views')` aggregates
            // the right attribute (Filament's `->summarize()` behavior).
            if ($summarizer->getColumn() === null) {
                $summarizer->column($this->name);
            }

            $this->summarizers[] = $summarizer;
        }

        return $this;
    }

    /**
     * @return array<int, Summarizer>
     */
    public function getSummarizers(): array
    {
        return $this->summarizers;
    }

    /**
     * Make the cell a link to the given URL. Accepts a static string or a
     * per-record closure. The placeholder `{record}` is replaced by the record
     * key in static URL strings.
     */
    public function url(string|Closure $url): static
    {
        $this->url = $url instanceof Closure
            ? $url
            : static function (mixed $record) use ($url): string {
                return str_contains($url, '{record}')
                    ? str_replace('{record}', (string) $record->getKey(), $url)
                    : $url;
            };

        return $this;
    }

    /**
     * Open the column's URL in a new browser tab.
     */
    public function openUrlInNewTab(bool $condition = true): static
    {
        if ($this->url === null) {
            throw new LogicException('openUrlInNewTab() requires a url() first.');
        }

        $this->openUrlInNewTab = $condition;

        return $this;
    }

    /**
     * Render an icon beside the value. Accepts a static icon key or a
     * per-record closure resolving to an icon key.
     */
    public function icon(string|Closure $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Color the icon (independent of the value color). Accepts a static name
     * or a per-record closure.
     */
    public function iconColor(string|Closure $iconColor): static
    {
        $this->iconColor = $iconColor;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * The header label. Longer dot-notation relationship names render as the
     * last segment ('author.name' => 'Name') unless an explicit label is set.
     */
    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        $name = (string) $this->name;

        $segment = str_contains($name, '.') ? Str::afterLast($name, '.') : $name;

        return Str::headline($segment);
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isToggleable(): bool
    {
        return $this->toggleable;
    }

    public function isBadge(): bool
    {
        return $this->isBadge;
    }

    public function isRelationship(): bool
    {
        return $this->name !== null && str_contains($this->name, '.');
    }

    public function hasDisplay(): bool
    {
        return $this->isBadge || $this->color !== null || $this->url !== null || $this->icon !== null || $this->iconColor !== null;
    }

    /**
     * Resolve a column's cell value for a record. A registered
     * getStateUsing() resolver wins; otherwise the record attribute named by
     * the column (via data_get, so dot-notation relationship columns resolve
     * the related attribute server-side). A formatStateUsing() formatting
     * closure then maps it to its display value. The closures never survive
     * serialization — the table resolver re-runs this when rows are served.
     */
    public function getStateFor(mixed $record): mixed
    {
        $state = $this->resolveRawState($record);

        return $this->formatUsing !== null ? ($this->formatUsing)($state, $record) : $state;
    }

    /**
     * Serialize the column definition (docs/CONTRACT.md, "Tables").
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
        ];

        if ($this->placeholder !== null) {
            $payload['placeholder'] = $this->placeholder;
        }

        if ($this->isSortable()) {
            $payload['sortable'] = true;
        }

        if ($this->isSearchable()) {
            $payload['searchable'] = true;
        }

        if ($this->isToggleable()) {
            $payload['toggleable'] = true;
        }

        if ($this->summarizers !== []) {
            // Only the presence ships — the computed values live on the
            // payload's `summary` map, keyed by column name.
            $payload['summarized'] = true;
        }

        // Display kinds publish server-side flags on the definition so the
        // client knows how to treat a column even when a specific record's
        // presentation resolves per record.
        if ($this->isBadge()) {
            $payload['badge'] = true;
        }

        if ($this->url !== null) {
            $payload['url'] = true;

            if ($this->openUrlInNewTab) {
                $payload['openUrlInNewTab'] = true;
            }
        }

        return $payload;
    }

    /**
     * Serialize one record's cell (docs/CONTRACT.md, "Tables"). Plain columns
     * return the formatted scalar value; display columns (badge / color /
     * icon / url) return a per-record structured object so their presentation
     * resolves server-side per record.
     *
     * @return mixed scalar (string|int|null) or array<string, mixed>
     */
    public function serializeCell(mixed $record): mixed
    {
        if (! $this->hasDisplay()) {
            return $this->getStateFor($record);
        }

        $cell = ['value' => $this->getStateFor($record)];

        if ($this->isBadge()) {
            $cell['badge'] = true;
        }

        $color = $this->resolveColorFor($record);

        if ($color !== null) {
            $cell['color'] = $color;
        }

        $icon = $this->resolveIconFor($record);

        if ($icon !== null) {
            $cell['icon'] = $icon;

            $iconColor = $this->resolveIconColorFor($record);

            if ($iconColor !== null) {
                $cell['iconColor'] = $iconColor;
            }
        }

        $url = $this->resolveUrlFor($record);

        if ($url !== null) {
            $cell['url'] = $url;
            $cell['openUrlInNewTab'] = $this->openUrlInNewTab;
        }

        return count($cell) === 1 ? $this->getStateFor($record) : $cell;
    }

    /**
     * The raw (unformatted) state of a record for this column.
     */
    private function resolveRawState(mixed $record): mixed
    {
        if ($this->stateResolver !== null) {
            return ($this->stateResolver)($record);
        }

        if ($this->name === null) {
            return null;
        }

        return $this->isRelationship()
            ? data_get($record, $this->name)
            : $record->getAttribute($this->name);
    }

    private function resolveColorFor(mixed $record): ?string
    {
        $color = $this->color;

        if ($color instanceof Closure) {
            $color = ($color)($this->resolveRawState($record));
        }

        if ($color === null || $color === false) {
            return null;
        }

        if (is_array($color)) {
            // A raw array (not a colors() closure) — pick the catch-all.
            foreach ($color as $key => $value) {
                if (is_numeric($key)) {
                    return (string) $value;
                }
            }

            return null;
        }

        return $color instanceof BackedEnum ? (string) $color->value : (string) $color;
    }

    private function resolveIconFor(mixed $record): ?string
    {
        $icon = $this->icon;

        if ($icon instanceof Closure) {
            $icon = ($icon)($record);
        }

        if ($icon === null || $icon === false || $icon === '') {
            return null;
        }

        return $icon instanceof BackedEnum ? (string) $icon->value : (string) $icon;
    }

    private function resolveIconColorFor(mixed $record): ?string
    {
        $color = $this->iconColor;

        if ($color instanceof Closure) {
            $color = ($color)($record);
        }

        if ($color === null || $color === false) {
            return null;
        }

        return $color instanceof BackedEnum ? (string) $color->value : (string) $color;
    }

    private function resolveUrlFor(mixed $record): ?string
    {
        $url = $this->url;

        if ($url instanceof Closure) {
            $url = ($url)($record);
        }

        return ($url === null || $url === '') ? null : (string) $url;
    }

    /**
     * Dot-notation relationship columns have no plain SQL column to sort or
     * search against without join logic, which is deferred
     * (docs/CONTRACT.md, "Tables").
     */
    private function assertDirectiveOnRelationship(string $directive): void
    {
        if ($this->isRelationship()) {
            throw new LogicException("Column [{$this->name}] cannot be {$directive} — dot-notation relationship columns need join logic, which is deferred.");
        }
    }
}
