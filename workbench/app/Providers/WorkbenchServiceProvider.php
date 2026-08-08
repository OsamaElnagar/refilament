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

        // The panel dashboard renders these widgets at /refilament (slice 1.9).
        $this->app['config']->set('refilament.panel.widgets', [
            ContentOverview::class,
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

        // Inertia's middleware must run inside the web group for the workbench
        // routes. Registered on "booted" so the middleware groups are already
        // configured by the framework.
        $this->app->booted(function (Application $app): void {
            $app->make('router')->prependMiddlewareToGroup('web', HandleInertiaRequests::class);
        });
    }
}
