<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use BackedEnum;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use Refilament\Refilament\Support\Concerns\HasAlignment;
use Refilament\Refilament\Support\Concerns\HasColor;
use Refilament\Refilament\Support\Concerns\HasExtraAttributes;
use Refilament\Refilament\Support\Concerns\HasFontFamily;
use Refilament\Refilament\Support\Concerns\HasIcon;
use Refilament\Refilament\Support\Concerns\HasIconColor;
use Refilament\Refilament\Support\Concerns\HasIconPosition;
use Refilament\Refilament\Support\Concerns\HasIconSize;
use Refilament\Refilament\Support\Concerns\HasLineClamp;
use Refilament\Refilament\Support\Concerns\HasPlaceholder;
use Refilament\Refilament\Support\Concerns\HasTooltip;
use Refilament\Refilament\Support\Concerns\HasWeight;
use Refilament\Refilament\Support\Concerns\HasWidth;
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
    use CanBeConfigured;
    use EvaluatesClosures;
    use HasAlignment;
    use HasColor;
    use HasExtraAttributes;
    use HasFontFamily;
    use HasIcon;
    use HasIconColor;
    use HasIconPosition;
    use HasIconSize;
    use HasLineClamp;
    use HasPlaceholder;
    use HasTooltip;
    use HasWeight;
    use HasWidth;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected bool $toggleable = false;

    /**
     * Server-side resolver for this column's raw cell state (e.g. a related
     * model attribute). Mirrors Filament's getStateUsing(); the closure never
     * survives serialization — the table resolver rebuilds it when rows are
     * served (docs/CONTRACT.md, "Tables").
     */
    protected ?Closure $stateResolver = null;

    /**
     * Inline-editable column (slice: editable columns). When true, the client
     * renders an inline control (checkbox/switch/select/text input) that
     * writes the column through the typed record-column update endpoint — a
     * stateless request/response, not a Livewire component. The column ships
     * `editable: true` on its definition; the value change is validated and
     * persisted server-side per request.
     */
    protected bool $isEditable = false;

    /** Optional per-record authorization for inline edits (never serialized). */
    protected ?Closure $editAuthorizer = null;

    /** Optional custom persistence handler for inline edits (never serialized). */
    protected ?Closure $stateUpdater = null;

    /** @var array<int, string> server-side validation rules for the edited value */
    protected array $editRules = [];

    /**
     * Server-side value formatter (Slice 2.1). Receives the resolved state
     * and the record; returns the display value (usually a string). Never
     * serialized — evaluated on every row.
     */
    protected ?Closure $formatUsing = null;

    protected string $prefix = '';

    protected string $suffix = '';

    protected bool $isBadge = false;

    /** @var string|Closure|null */
    protected mixed $url = null;

    protected bool $openUrlInNewTab = false;

    /** @var array<int, Summarizer> */
    protected array $summarizers = [];

    final public function __construct(protected ?string $name = null)
    {
        $this->configure();
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Treat the header label as a translation key resolved through the app's
     * translator when the column is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

        return $this;
    }

    /**
     * Mark the column as sortable. Clients send `sort` / `direction` query
     * params to the index endpoint, which orders the query server-side
     * (docs/CONTRACT.md, "Tables"). A relationship (dot-notation) column is
     * ordered by a correlated subquery over its related table (Slice 2.1).
     */
    public function sortable(bool $condition = true): static
    {
        $this->sortable = $condition;

        return $this;
    }

    /**
     * Mark the column as searchable: the global `search` query param matches
     * against it with a `LIKE` clause (docs/CONTRACT.md, "Tables"). A
     * relationship (dot-notation) column matches via Eloquent's native
     * `whereRelation` (Slice 2.1).
     */
    public function searchable(bool $condition = true): static
    {
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
     * Prepend a static prefix to the displayed value. Applied after any
     * formatter so it composes with suffix() and formatStateUsing().
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Append a static suffix to the displayed value. Applied after any
     * formatter so it composes with prefix() and formatStateUsing().
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
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
     * Mark the column as inline-editable: the client renders a control that
     * writes the column through the typed record-column update endpoint.
     */
    public function editable(bool $condition = true): static
    {
        $this->isEditable = $condition;

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->isEditable;
    }

    /**
     * Optional per-record authorization for inline edits. The closure receives
     * the record; returning false refuses the write even when the client sends
     * it. Never serialized — evaluated server-side per request.
     */
    public function canEdit(Closure $authorizer): static
    {
        $this->editAuthorizer = $authorizer;

        return $this;
    }

    public function isAuthorizedFor(mixed $record): bool
    {
        if ($this->editAuthorizer === null) {
            return true;
        }

        return (bool) $this->evaluate(
            $this->editAuthorizer,
            ['record' => $record],
            $this->recordTypeInjections($record),
        );
    }

    /**
     * Custom persistence handler for an inline edit. The closure receives the
     * record and the new value; when absent the column mass-assigns the value
     * to its named attribute. Never serialized — evaluated server-side.
     */
    public function updateStateUsing(Closure $updater): static
    {
        $this->stateUpdater = $updater;

        return $this;
    }

    /**
     * Server-side validation rules for the edited value (Laravel rules —
     * pipe strings or arrays). Enforced by the update endpoint before the
     * value is persisted.
     *
     * @param  array<int, string>|string  $rules
     */
    public function rules(array|string $rules): static
    {
        $this->editRules = array_values(is_string($rules) ? explode('|', $rules) : $rules);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getEditRules(): array
    {
        return $this->editRules;
    }

    /**
     * Persist an inline-edited value to the record.
     */
    public function updateState(mixed $record, mixed $value): void
    {
        if ($this->stateUpdater !== null) {
            $this->evaluate(
                $this->stateUpdater,
                ['record' => $record, 'state' => $value],
                $this->recordTypeInjections($record),
            );

            return;
        }

        $record->update([(string) $this->name => $value]);
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
        $label = $this->resolveLabel();

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    protected function resolveLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        $name = (string) $this->name;

        $segment = str_contains($name, '.') ? Str::afterLast($name, '.') : $name;

        return Str::headline($segment);
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

        $state = $this->formatUsing !== null
            ? $this->evaluate(
                $this->formatUsing,
                ['state' => $state, 'record' => $record],
                $this->recordTypeInjections($record),
            )
            : $state;

        if ($this->prefix !== '') {
            $state = $this->prefix.(string) ($state ?? '');
        }

        if ($this->suffix !== '') {
            $state = (string) ($state ?? '').$this->suffix;
        }

        return $state;
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

        $placeholder = $this->getPlaceholder();

        if ($placeholder !== null) {
            $payload['placeholder'] = $placeholder;
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

        if ($this->isEditable()) {
            $payload['editable'] = true;
        }

        // Presentation options (Slice — column presentation). Each getter
        // resolves through EvaluatesClosures, so a closure config yields its
        // value here (no state injection for these — they are column-level).
        $tooltip = $this->getTooltip();

        if ($tooltip !== null) {
            $payload['tooltip'] = $tooltip;
        }

        $alignment = $this->getAlignment();

        if ($alignment !== null) {
            $payload['alignment'] = $alignment instanceof BackedEnum ? $alignment->value : $alignment;
        }

        $width = $this->getWidth();

        if ($width !== null) {
            $payload['width'] = $width;
        }

        $weight = $this->getWeight();

        if ($weight !== null) {
            $payload['weight'] = $weight instanceof BackedEnum ? $weight->value : $weight;
        }

        $fontFamily = $this->getFontFamily();

        if ($fontFamily !== null) {
            $payload['fontFamily'] = $fontFamily instanceof BackedEnum ? $fontFamily->value : $fontFamily;
        }

        $lineClamp = $this->getLineClamp();

        if ($lineClamp !== null) {
            $payload['lineClamp'] = $lineClamp;
        }

        if ($this->hasIconPosition()) {
            $payload['iconPosition'] = $this->getIconPosition()->value;
        }

        $iconSize = $this->getIconSize();

        if ($iconSize !== null) {
            $payload['iconSize'] = $iconSize instanceof BackedEnum ? $iconSize->value : $iconSize;
        }

        $extraAttributes = $this->getExtraAttributes();

        if ($extraAttributes !== []) {
            $payload['extraAttributes'] = $extraAttributes;
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
            return $this->evaluate(
                $this->stateResolver,
                ['record' => $record],
                $this->recordTypeInjections($record),
            );
        }

        if ($this->name === null) {
            return null;
        }

        return $this->isRelationship()
            ? data_get($record, $this->name)
            : $record->getAttribute($this->name);
    }

    protected function resolveColorFor(mixed $record): ?string
    {
        // Evaluated through a local so the closure's result type stays open
        // (mixed) — a per-record color closure may legitimately resolve to
        // false or a BackedEnum, and the guards below handle those.
        $color = $this->color;

        if ($color instanceof Closure) {
            $color = $this->evaluate($color, ['state' => $this->resolveRawState($record)]);
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

    protected function resolveIconFor(mixed $record): ?string
    {
        $icon = $this->evaluate(
            $this->icon,
            ['record' => $record, 'state' => $this->resolveRawState($record)],
            $this->recordTypeInjections($record),
        );

        if ($icon === null || $icon === false || $icon === '') {
            return null;
        }

        return $icon instanceof BackedEnum ? (string) $icon->value : (string) $icon;
    }

    protected function resolveIconColorFor(mixed $record): ?string
    {
        $color = $this->iconColor;

        if ($color instanceof Closure) {
            $color = $this->evaluate($color, ['record' => $record], $this->recordTypeInjections($record));
        }

        if ($color === null || $color === false) {
            return null;
        }

        return $color instanceof BackedEnum ? (string) $color->value : (string) $color;
    }

    private function resolveUrlFor(mixed $record): ?string
    {
        $url = $this->evaluate(
            $this->url,
            ['record' => $record],
            $this->recordTypeInjections($record),
        );

        return ($url === null || $url === '') ? null : (string) $url;
    }

    /**
     * The relationship path of a dot-notation column (`user.name` => `user`),
     * or null for a plain column. Used to build the correlated subquery for
     * relationship sort and the `whereRelation` for relationship search
     * (Slice 2.1).
     */
    public function getRelationshipName(): ?string
    {
        if ($this->name === null || ! $this->isRelationship()) {
            return null;
        }

        return Str::beforeLast($this->name, '.');
    }

    /**
     * The final attribute segment of a dot-notation column (`user.name` =>
     * `name`), or the whole column name for a plain column.
     */
    public function getAttributeName(): ?string
    {
        if ($this->name === null) {
            return null;
        }

        return $this->isRelationship() ? Str::afterLast($this->name, '.') : $this->name;
    }
}
