<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

use BackedEnum;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use LogicException;
use Refilament\Refilament\Schemas\Components\Component;

/**
 * Infolist entry (slice 3.3 — docs/ROADMAP.md).
 *
 * A read-only record display: a labeled value rendered from the record the
 * enclosing schema is bound to (`Schema::record($record)`, mirroring
 * Filament's `->record()`). Entries compose like fields — they can live
 * inside any layout (Grid, Section, ...) and resolve their value server-side
 * at serialization, so formatting closures never survive the wire.
 *
 * Mirrors `Filament\Infolists\Components\Entry`. Value formatting (money /
 * date / numeric / ...) and display kinds (badge / color / icon / url) reuse
 * the same server-side idioms as table Columns, so an infolist and a table
 * present a record consistently. The shipped node is a read-only contract
 * node with the already-resolved, already-formatted `value`.
 */
abstract class Entry extends Component
{
    protected ?string $placeholder = null;

    /**
     * Server-side resolver for this entry's raw state (e.g. a related model
     * attribute). Mirrors Filament's getStateUsing(); the closure never
     * survives serialization — the schema resolver rebuilds it per request.
     *
     * @var Closure(mixed): mixed|null
     */
    protected ?Closure $stateResolver = null;

    /**
     * Server-side value formatter. Receives the resolved state and the
     * record; returns the display value (usually a string). Never serialized.
     *
     * @var Closure(mixed, mixed): mixed|null
     */
    protected ?Closure $formatUsing = null;

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

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Register a closure that resolves this entry's state from the record —
     * typically a related model attribute, e.g.
     * `fn (Post $record): ?string => $record->user?->name`. The closure
     * never survives serialization; the schema resolver rebuilds it when the
     * infolist is served.
     *
     * @param  Closure(mixed $record): mixed  $resolver
     */
    public function getStateUsing(Closure $resolver): static
    {
        $this->stateResolver = $resolver;

        return $this;
    }

    /**
     * Register a server-side formatter for this entry's state. The closure
     * receives the (already resolved) raw state and the record, and returns
     * the displayed value. Mirrors Filament's formatStateUsing().
     *
     * @param  Closure(mixed $state, mixed $record): mixed  $formatter
     */
    public function formatStateUsing(Closure $formatter): static
    {
        $this->formatUsing = $formatter;

        return $this;
    }

    /**
     * Format the state as a currency amount, e.g. "$1,234.56". Mirrors
     * Filament's money() and Column::money().
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
     * Format the state as a date (Carbon::translatedFormat).
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
     * Format the state as a number with grouped thousands.
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
     * Truncate the displayed value to a character limit.
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
     * Render the value as a shadcn Badge. Combine with color() for the
     * badge's color.
     */
    public function badge(bool $condition = true): static
    {
        $this->isBadge = $condition;

        return $this;
    }

    /**
     * Color the value (text or badge). Accepts a static name, an array map,
     * or a per-record closure resolving the color from the state.
     *
     * @param  string|array<int|string, string|Closure>|Closure  $color
     */
    public function color(string|array|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * A state → color mapping (the `->badge()->color(fn ...)` idiom's static
     * cousin). Mirrors Column::colors().
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
     * Make the value a link to the given URL. Accepts a static string or a
     * per-record closure. The placeholder `{record}` is replaced by the
     * record key in static URL strings.
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
     * Open the entry's URL in a new browser tab.
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
     * Color the icon (independent of the value color).
     */
    public function iconColor(string|Closure $iconColor): static
    {
        $this->iconColor = $iconColor;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function isBadge(): bool
    {
        return $this->isBadge;
    }

    public function isRelationship(): bool
    {
        return $this->getName() !== null && str_contains((string) $this->getName(), '.');
    }

    /**
     * Resolve the entry's display value for the bound record. A registered
     * getStateUsing() resolver wins; otherwise the record attribute named by
     * the entry (via data_get, so dot-notation relationship entries resolve
     * the related attribute). A formatStateUsing() closure then maps it to
     * its display value.
     */
    public function getStateFor(mixed $record): mixed
    {
        $state = $this->resolveRawState($record);

        return $this->formatUsing !== null ? ($this->formatUsing)($state, $record) : $state;
    }

    /**
     * Serialize one entry node (docs/CONTRACT.md, "Infolists"). Resolves the
     * value (and any per-record presentation) against the bound record, so
     * the shipped node is a fully-resolved read-only contract node.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $record = $this->getRecord();

        $payload = [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'value' => $record !== null ? $this->getStateFor($record) : null,
        ];

        if ($this->placeholder !== null) {
            $payload['placeholder'] = $this->placeholder;
        }

        if ($this->columnSpan !== null) {
            $payload['columnSpan'] = $this->columnSpan;
        }

        if ($record !== null) {
            if ($this->isBadge()) {
                $payload['badge'] = true;
            }

            $color = $this->resolveColorFor($record);

            if ($color !== null) {
                $payload['color'] = $color;
            }

            $icon = $this->resolveIconFor($record);

            if ($icon !== null) {
                $payload['icon'] = $icon;

                $iconColor = $this->resolveIconColorFor($record);

                if ($iconColor !== null) {
                    $payload['iconColor'] = $iconColor;
                }
            }

            $url = $this->resolveUrlFor($record);

            if ($url !== null) {
                $payload['url'] = $url;
                $payload['openUrlInNewTab'] = $this->openUrlInNewTab;
            }
        }

        return $payload;
    }

    /**
     * The raw (unformatted) state of the record for this entry.
     */
    private function resolveRawState(mixed $record): mixed
    {
        if ($this->stateResolver !== null) {
            return ($this->stateResolver)($record);
        }

        $name = $this->getName();

        if ($name === null) {
            return null;
        }

        return $this->isRelationship()
            ? data_get($record, $name)
            : $record->getAttribute($name);
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
}
