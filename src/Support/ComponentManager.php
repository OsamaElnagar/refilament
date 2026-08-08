<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support;

use Closure;

/**
 * The registry behind `X::configureUsing()` (production reference §1.1 —
 * mirrors Filament's `Components\ComponentManager`). Configurations are keyed
 * by class, and the hierarchy walk means a default registered on a parent
 * class (e.g. `Component`, `BulkAction`) applies to every subclass — the
 * "scale the whole app at once" lever real Filament apps use.
 *
 * `configure()` runs a builder through the pipeline at construction:
 *
 *   1. every registered (non-important) default, parent classes first — this
 *      happens *before* the builder's own fluent calls, so per-instance
 *      configuration always wins;
 *   2. every "important" default, which runs *after* the builder's calls and
 *      therefore wins over them.
 *
 * The consumer surface is the `CanBeConfigured` trait on each builder;
 * `configureUsing()` also supports Filament's two scoping tricks — a
 * `$during` closure (the registration only lives for that closure's
 * execution) and the returned teardown closure (call it to unregister), both
 * of which make tests leak-free.
 */
final class ComponentManager
{
    // Keyed by class name (a class-string in intent). Plain `string` keys
    // keep PHPStan's flow analysis happy on the local write-backs below —
    // `??=`/`[]=`/`array_pop` through a typed static property otherwise
    // narrows the property type and rejects the reassignment.
    /** @var array<string, array<int, Closure(object): void>> */
    protected static array $configurations = [];

    /** @var array<string, array<int, Closure(object): void>> */
    protected static array $importantConfigurations = [];

    /**
     * @param  Closure(object): void  $modifyUsing
     * @param  Closure(): mixed|null  $during
     * @return mixed The closure's result when `$during` is given, else a
     *               teardown closure that unregisters this configuration.
     */
    public static function configureUsing(string $component, Closure $modifyUsing, ?Closure $during = null, bool $isImportant = false): mixed
    {
        // Mutations go through a local copy and are written back, so PHPStan
        // never narrows the static property's flow type across `??=`/`[]=`
        // (it does for typed statics) and reports a bogus incompatibility.
        $configurations = $isImportant ? self::$importantConfigurations : self::$configurations;

        $configurations[$component] ??= [];
        $configurations[$component][] = $modifyUsing;

        if ($isImportant) {
            self::$importantConfigurations = $configurations;
        } else {
            self::$configurations = $configurations;
        }

        if ($during === null) {
            $configurationKey = $isImportant
                ? array_key_last(self::$importantConfigurations[$component])
                : array_key_last(self::$configurations[$component]);

            // The returned teardown closure unregisters the default — handy in
            // tests, and exactly what Filament's configureUsing() returns.
            return function () use ($component, $configurationKey, $isImportant): void {
                if ($isImportant) {
                    unset(self::$importantConfigurations[$component][$configurationKey]);

                    return;
                }

                unset(self::$configurations[$component][$configurationKey]);
            };
        }

        try {
            return $during();
        } finally {
            if ($isImportant) {
                $important = self::$importantConfigurations;
                array_pop($important[$component]);
                self::$importantConfigurations = $important;
            } else {
                $configurations = self::$configurations;
                array_pop($configurations[$component]);
                self::$configurations = $configurations;
            }
        }
    }

    /**
     * Apply every registered default for the component's class hierarchy to
     * the instance — normal defaults (parent first), then important defaults.
     */
    public static function configure(object $component): void
    {
        $classesToConfigure = [...array_reverse(class_parents($component) ?: []), $component::class];

        foreach ($classesToConfigure as $classToConfigure) {
            foreach (self::$configurations[$classToConfigure] ?? [] as $configure) {
                $configure($component);
            }
        }

        foreach ($classesToConfigure as $classToConfigure) {
            foreach (self::$importantConfigurations[$classToConfigure] ?? [] as $configure) {
                $configure($component);
            }
        }
    }

    /**
     * Forget every registered default. Tests call this in beforeEach so a
     * configured default never leaks into a later test's assertions.
     */
    public static function flush(): void
    {
        self::$configurations = [];
        self::$importantConfigurations = [];
    }
}
