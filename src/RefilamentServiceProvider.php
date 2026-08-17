<?php

declare(strict_types=1);

namespace Refilament\Refilament;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Refilament\Refilament\Console\Commands\InstallCommand;
use Refilament\Refilament\Console\Commands\MakeClusterCommand;
use Refilament\Refilament\Console\Commands\MakePageCommand;
use Refilament\Refilament\Console\Commands\MakeResourceCommand;
use Refilament\Refilament\Console\Commands\MakeSingularResourceCommand;
use Refilament\Refilament\Console\Commands\RefilamentCommand;
use Refilament\Refilament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Refilament\Refilament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Refilament\Refilament\Support\Colors\ColorManager;
use Refilament\Refilament\Support\Icons\Heroicon;
use Refilament\Refilament\Support\Icons\IconManager;

class RefilamentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/refilament.php', 'refilament');

        // Fortify is the panel's auth engine (docs/ROADMAP.md "1.9 auth
        // pages"), but the panel owns the routes — unless the consumer has
        // published their own fortify config (they own Fortify then), keep
        // Fortify's own view routes from registering at the app root, where
        // they would render a missing Blade view on /login.
        if (! file_exists(config_path('fortify.php'))) {
            config(['fortify.views' => false]);
        }

        $this->app->singleton(Refilament::class);

        // The global search provider (slice 3.5) — bound to the default
        // provider but overridable by the app via the Contracts interface.
        $this->app->bind(
            GlobalSearchProvider::class,
            fn (): DefaultGlobalSearchProvider => new DefaultGlobalSearchProvider($this->app->make(Refilament::class)),
        );

        // The icon registry behind the `RefilamentIcon` facade (slice —
        // mirrors Filament's scoped `IconManager`). Scoped so a per-request
        // registration never leaks across requests.
        $this->app->scoped(
            IconManager::class,
            fn (): IconManager => new IconManager,
        );

        // The named-color registry behind the `RefilamentColor` facade
        // (mirrors Filament's scoped `ColorManager`). Scoped so a per-request
        // registration never leaks across requests.
        $this->app->scoped(
            ColorManager::class,
            fn (): ColorManager => new ColorManager,
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

        // Seed the icon registry with the default catalog so the facade
        // resolves every known canonical key out of the box. Aliases can be
        // overridden by consumers via `RefilamentIcon::register(...)`.
        $this->app->make(IconManager::class)->register(
            collect(Heroicon::cases())
                ->mapWithKeys(fn (Heroicon $icon): array => [$icon->value => $icon])
                ->all(),
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
            $refilament->registerAuthRoutes();
        });

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'refilament');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'refilament');

        // The panel shell reads its sidebar navigation from the shared
        // "refilament" prop (slice 1.9 — docs/ROADMAP.md "1.9 Panel shell"),
        // reassembled on every request so registered resources are reflected.
        // The lazily-built panel is resolved per-request so sharing it here —
        // before the console guard below — also serves it to web requests.
        // The authenticated user (name/email) rides along under `user` so the
        // shell's user menu can greet and identify the visitor; it's absent
        // entirely for guests, so the menu simply doesn't render.
        Inertia::share('refilament', static function () use ($refilament): array {
            $user = $refilament->authorizationUser();

            /** @var object{name?: mixed, email?: mixed}|null $u */
            $u = $user;

            return [
                'panel' => $refilament->panel()->toArray(),
                ...($u !== null ? ['user' => [
                    'name' => (string) ($u->name ?? ''),
                    'email' => (string) ($u->email ?? ''),
                ]] : []),
            ];
        });

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
            MakeClusterCommand::class,
            MakePageCommand::class,
            MakeResourceCommand::class,
            MakeSingularResourceCommand::class,
            RefilamentCommand::class,
        ]);
    }
}
