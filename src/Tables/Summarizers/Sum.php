<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Summarizers;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Sum summarizer — the footer total of a numeric column, computed over the
 * table's filtered query (`->summarize(Sum::make()->label('Total views'))`).
 * Mirrors Filament's Sum and the Ahram report pages.
 */
class Sum extends Summarizer
{
    public function summarize(Builder $query, string $column): int|float|string|null
    {
        return $query->sum($column);
    }
}
