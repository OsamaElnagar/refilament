<?php

declare(strict_types=1);

namespace Refilament\Refilament;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Refilament\Refilament\Console\Commands\InstallCommand;
use Refilament\Refilament\Console\Commands\MakePageCommand;
use Refilament\Refilament\Console\Commands\MakeResourceCommand;
use Refilament\Refilament\Console\Commands\RefilamentCommand;
use Refilament\Refilament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Refilament\Refilament\GlobalSearch\Providers\DefaultGlobalSearchProvider;

class RefilamentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/refilament.php', 'refilament');

        $this->app->singleton(Refilament::class);

        // The global search provider (slice 3.5) — bound to the default
        // provider but overridable by the app via the Contracts interface.
        $this->app->bind(
            GlobalSearchProvider::class,
            fn (): DefaultGlobalSearchProvider => new DefaultGlobalSearchProvider($this->app->make(Refilament::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Discover resources from the configured directory so their tables and
        // forms are served through the typed endpoints automatically
        // (docs/ARCHITECTURE.md, "Resources").
        $refilament = $this->app->make(Refilament::class);
        $refilament->registerResourcesFromDirectory(
            (string) config('refilament.resources.path'),
            (string) config('refilament.resources.namespace'),
        );

        // Register every package route and page route under the panel's URL
        // prefix. This runs from a `booted()` hook — after every provider has
        // registered and booted — so a consumer's PanelProvider (which
        // registers its `panel()` override during provider registration, and
        // whose provider class sits in bootstrap/providers.php after the
        // auto-discovered package providers) is already known when the panel
        // path is resolved. A consumer's `->path('admin')` therefore moves
        // the routes with it.
        $this->app->booted(static function () use ($refilament): void {
            $refilament->registerRoutes();
            $refilament->registerPageRoutes();
        });

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'refilament');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'refilament');

        // The panel shell reads its sidebar navigation from the shared
        // "refilament" prop (slice 1.9 — docs/ROADMAP.md "1.9 Panel shell"),
        // reassembled on every request so registered resources are reflected.
        // The lazily-built panel is resolved per-request so sharing it here —
        // before the console guard below — also serves it to web requests.
        Inertia::share('refilament', static fn () => ['panel' => $refilament->panel()->toArray()]);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/refilament.php' => config_path('refilament.php'),
        ], ['refilament', 'refilament-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/refilament'),
        ], ['refilament', 'refilament-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/refilament'),
        ], ['refilament', 'refilament-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/refilament'),
        ], ['refilament', 'refilament-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['refilament', 'refilament-migrations']);

        $this->commands([
            InstallCommand::class,
            MakePageCommand::class,
            MakeResourceCommand::class,
            RefilamentCommand::class,
        ]);
    }
}
