<?php

declare(strict_types=1);

namespace Refilament\Refilament;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Refilament\Refilament\Console\Commands\MakePageCommand;
use Refilament\Refilament\Console\Commands\MakeResourceCommand;
use Refilament\Refilament\Console\Commands\RefilamentCommand;

class RefilamentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/refilament.php', 'refilament');

        $this->app->singleton(Refilament::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/refilament.php');

        // Discover resources from the configured directory so their tables and
        // forms are served through the typed endpoints automatically
        // (docs/ARCHITECTURE.md, "Resources").
        $refilament = $this->app->make(Refilament::class);
        $refilament->registerResourcesFromDirectory(
            (string) config('refilament.resources.path'),
            (string) config('refilament.resources.namespace'),
        );

        // Auto-register one page route per page name in every discovered
        // resource's getPages() map (slice 1.6 — docs/ROADMAP.md "1.6 Page
        // system"): GET /refilament/{resource}{path} for each page slot,
        // named refilament.resource.{page} and served by the single
        // ResourcePageController, which resolves the page class from the
        // route name's trailing segment and the resource from the URL.
        // Each route is where()-gated to the ids discovered at boot (never an
        // unconstrained catch-all, so app-owned routes like
        // /refilament/playground are not shadowed) and constrains its
        // {record} segment to [0-9]+. The where() list — not the manager's
        // id list — is the operative gate deciding which URLs reach these
        // routes, so any future late-registration support must keep both in
        // sync. The page classes themselves build the routes
        // (Resources\Pages\Page::route()), mirroring Filament's
        // PageRegistration.
        $refilament->registerPageRoutes();

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
            MakePageCommand::class,
            MakeResourceCommand::class,
            RefilamentCommand::class,
        ]);
    }
}
