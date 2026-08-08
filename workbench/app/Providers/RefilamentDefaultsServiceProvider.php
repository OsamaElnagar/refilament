<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Summarizers\Sum;

/**
 * The production-reference §1.1 / §1.2 demo — Refilament's answer to Heaven's
 * FilamentDefaultsServiceProvider.
 *
 * - `X::configureUsing(...)` registers a global default that runs on *every*
 *   instance of X at construction, before the builder's own fluent calls —
 *   per-instance configuration still wins (the whole ERP gets a consistent
 *   look with zero per-table repetition, exactly like Heaven's Table default).
 * - `X::macro('egp', ...)` extends the builders' fluent API with a domain
 *   verb used everywhere (`->egp()` on a Column, a Sum) — Heaven's money
 *   macro pattern.
 *
 * Guarded by runningUnitTests(): the pest suite asserts exact serialized
 * payloads, so these global defaults are a live-demo concern only (serve).
 */
class RefilamentDefaultsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // §1.2 — macros: one domain verb, every context. `->egp()` formats a
        // column or a footer summarizer as Egyptian pounds in one word.
        // Registered unconditionally: a macro only affects builders that opt
        // in via `->egp()`, so under the pest suite it is inert (the posts
        // table's revenue column just renders as currency in its payloads).
        Column::macro('egp', fn (): Column => $this->money('EGP', divideBy: 1)->color('primary'));
        Sum::macro('egp', fn (): Sum => $this->money('EGP', locale: 'en', decimalPlaces: 0));

        // §1.1 — global defaults are behavior-changing for *every* instance
        // (every column in the demo becomes toggleable), so they are a
        // live-demo concern only: the pest suite asserts exact serialized
        // column payloads and must not see them.
        if ($this->app->runningUnitTests()) {
            return;
        }

        Column::configureUsing(static fn (Column $column): Column => $column->toggleable());
    }
}
