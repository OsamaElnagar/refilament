<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Http\Middleware\HandleInertiaRequests;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Resources\RelationManagers\CommentsRelationManager;
use Workbench\App\Refilament\Widgets\ContentOverview;
use Workbench\App\Refilament\Widgets\PostsStatusChart;
use Workbench\App\Refilament\Widgets\RecentPostsTableWidget;
use Workbench\App\Support\PlaygroundSchema;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services. Runs before every provider's boot(), so the resource
     * discovery path is in place before the package provider scans it.
     */
    public function register(): void
    {
        // Point resource discovery at the workbench app's resource directory
        // (the package default targets a real app's app/Refilament/Resources).
        $this->app['config']->set('refilament.resources.path', dirname(__DIR__, 2).'/app/Refilament/Resources');
        $this->app['config']->set('refilament.resources.namespace', 'Workbench\\App\\Refilament\\Resources');

        // Same for standalone panel pages (slice 1.9 "->pages([...])") — point
        // discovery at the workbench's page folder and namespace.
        $this->app['config']->set('refilament.panel.pages_path', dirname(__DIR__, 2).'/app/Refilament/Pages');
        $this->app['config']->set('refilament.panel.pages_namespace', 'Workbench\\App\\Refilament\\Pages');

        // The panel dashboard renders these widgets at /refilament (slice 1.9).
        $this->app['config']->set('refilament.panel.widgets', [
            ContentOverview::class,
            RecentPostsTableWidget::class,
        ]);
    }

    /**
     * Bootstrap the workbench application.
     */
    public function boot(): void
    {
        // Point the workbench app's public path at the package's own
        // workbench/public so the Vite manifest and hot file resolve inside
        // the package (see docs/ARCHITECTURE.md, "Frontend delivery").
        $this->app->usePublicPath(dirname(__DIR__, 2).'/public');

        // Register the playground schema document under its id so the typed
        // resolve-options endpoint can rebuild it (closures included) when a
        // dependent field's options are requested.
        $this->app->make(Refilament::class)->registerSchemaResolver(
            'playground',
            static fn (): Schema => PlaygroundSchema::make(),
        );

        // The comments relation's form schema document (slice 1.8) is served
        // straight from the relation manager's standalone CommentsForm class —
        // the create/edit modals fetch it by id and submit against the typed
        // relation action endpoint, which reuses the same form for validation.
        $this->app->make(Refilament::class)->registerSchemaResolver(
            'comment-form',
            static fn (): Schema => CommentsRelationManager::form(Schema::make()),
        );

        // The posts table and post form come from the discovered PostResource
        // (workbench/app/Refilament/Resources) — no manual registration.

        // A table whose query bakes in its own ordering — a requested sort
        // must still win (reorder), never demote to a tiebreaker.
        // The demo chart widget exposes live data (slice 3.2): its id is the
        // typed widget data endpoint's address, and the resolver rebuilds the
        // widget per request so the filter + data closures stay server-side.
        $this->app->make(Refilament::class)->registerWidgetResolver(
            'posts-status-chart',
            static fn (): PostsStatusChart => PostsStatusChart::demo(),
        );

        $this->app->make(Refilament::class)->registerTable(
            'posts-baked',
            static fn (): Table => Table::make()
                ->id('posts-baked')
                ->columns([
                    Column::make('title')->label('Title')->sortable(),
                    Column::make('views')->label('Views')->sortable(),
                ])
                ->query(Post::query()->orderByDesc('views')),
        );

        // A table widget's table must be resolvable by its id (slice D1): the
        // widget embeds the first page for the initial render, and every sort /
        // pagination interaction rebuilds it through the typed table endpoint.
        $this->app->make(Refilament::class)->registerTable(
            'recent-posts-table',
            static fn (): Table => RecentPostsTableWidget::make()->getWidgetTable(),
        );

        // Shell render hook (slice B1): the sidebar footer renders the
        // 'quick-links' component the workbench app registers client-side
        // (resources/js/components/shell/QuickLinks.tsx). Declaring the hook
        // is what arms the slot — the panel payload ships the enabled list,
        // and the React runtime mounts the registered component.
        $this->app->make(Refilament::class)->panel()
            ->renderHook('sidebar-footer', 'quick-links')
            // Database-notifications bell (slice B3): the shell polls the
            // typed notifications endpoint every 10s for the unread count
            // and latest rows, mirroring Filament's databaseNotifications().
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s');

        // Inertia's middleware must run inside the web group for the workbench
        // routes. Registered on "booted" so the middleware groups are already
        // configured by the framework.
        $this->app->booted(function (Application $app): void {
            $app->make('router')->prependMiddlewareToGroup('web', HandleInertiaRequests::class);
        });
    }
}
