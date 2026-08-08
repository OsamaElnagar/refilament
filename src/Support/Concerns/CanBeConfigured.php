<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;
use Refilament\Refilament\Support\ComponentManager;

/**
 * The `X::configureUsing()` / `$this->configure()` surface (production
 * reference §1.1 — mirrors Filament's `Support\Concerns\Configurable`).
 *
 * `X::configureUsing(fn (X $x) => $x->…)` registers a global default that runs
 * on *every* instance of X — and of every subclass, via the hierarchy walk in
 * ComponentManager::configure() — at construction, *before* the builder's own
 * fluent calls. Per-instance configuration therefore always wins, exactly as
 * Heaven's FilamentDefaultsServiceProvider relies on.
 *
 * The returned teardown closure unregisters the default, and the `$during`
 * closure scopes a default to a single block of execution — both keep tests
 * leak-free. `$isImportant` resolves conflicts between defaults themselves:
 * important defaults apply after normal ones inside the construction pipeline.
 */
trait CanBeConfigured
{
    public static function configureUsing(Closure $modifyUsing, ?Closure $during = null, bool $isImportant = false): mixed
    {
        return ComponentManager::configureUsing(static::class, $modifyUsing, $during, $isImportant);
    }

    public function configure(): static
    {
        ComponentManager::configure($this);

        return $this;
    }
}
