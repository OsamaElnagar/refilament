<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Summarizers;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;

/**
 * Table footer summary (slice 1.7 — docs/ROADMAP.md "1.7 Record pages").
 *
 * A summarizer computes one aggregate value over a table's *filtered* query
 * (never a single page) and renders it in the table's footer row. Register
 * one on a column:
 *
 *     Column::make('views')->summarize(Sum::make()->label('Total views'))
 *
 * Mirrors Filament's table summaries and the Ahram report pages
 * (`->summarize(Sum::make()->money('EGP', decimalPlaces: 0)->label('...'))`).
 * The closure-based formatters never survive serialization — the table
 * resolver computes each summarizer's state per request and ships the
 * already-formatted string.
 */
abstract class Summarizer
{
    use CanBeConfigured;
    use Macroable;

    /** @var Closure(Builder): (int|float|string|null) */
    protected Closure $stateResolver;

    protected ?string $label = null;

    /** @var Closure(mixed): mixed|null */
    protected ?Closure $formatUsing = null;

    final public function __construct(protected ?string $column = null)
    {
        $this->stateResolver = function (Builder $query): int|float|string|null {
            return $this->summarize($query, (string) $this->column);
        };

        $this->configure();
    }

    public static function make(?string $column = null): static
    {
        return new static($column);
    }

    /**
     * The column this summarizer aggregates. When built standalone
     * (`Sum::make('views')`) the constructor sets it; when attached via
     * `Column::summarize(Sum::make())` the column's name is inherited at
     * configuration time.
     */
    public function column(?string $column): static
    {
        $this->column = $column;

        return $this;
    }

    /**
     * The label rendered beside the footer value. Defaults to the column's
     * headline unless set explicitly.
     */
    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline((string) $this->column);
    }

    /**
     * Compute this summarizer's aggregate against the query.
     *
     * @param  Builder  $query  the table's filtered query
     */
    abstract public function summarize(Builder $query, string $column): int|float|string|null;

    /**
     * Register a server-side formatter for the aggregate value. Never
     * survives serialization — evaluated per request when the payload is
     * built.
     *
     * @param  Closure(mixed): mixed  $formatter
     */
    public function formatStateUsing(Closure $formatter): static
    {
        $this->formatUsing = $formatter;

        return $this;
    }

    /**
     * Format the aggregate as a currency amount, e.g. "$1,234.56" — the
     * Ahram report idiom (`Sum::make()->money('EGP', decimalPlaces: 0)`).
     */
    public function money(?string $currency = null, ?string $locale = null, ?int $decimalPlaces = null): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($currency, $locale, $decimalPlaces): string {
            if (! is_numeric($state)) {
                return (string) $state;
            }

            return (string) Number::currency((float) $state, $currency ?? 'USD', $locale, $decimalPlaces);
        });
    }

    /**
     * Format the aggregate as a number with grouped thousands.
     */
    public function numeric(?int $decimalPlaces = null): static
    {
        return $this->formatStateUsing(static function (mixed $state) use ($decimalPlaces): string {
            if (! is_numeric($state)) {
                return (string) $state;
            }

            return (string) Number::format((float) $state, $decimalPlaces);
        });
    }

    /**
     * Resolve the formatted aggregate state for the payload.
     */
    public function getState(Builder $query): mixed
    {
        $state = ($this->stateResolver)($query);

        return $this->formatUsing !== null ? ($this->formatUsing)($state) : $state;
    }
}
