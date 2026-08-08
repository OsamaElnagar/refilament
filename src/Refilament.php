<?php

declare(strict_types=1);

namespace Refilament\Refilament;

use Closure;
use FilesystemIterator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route as RouteFacade;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Refilament\Refilament\Http\Controllers\PanelPageController;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Resources\RelationManagers\RelationManager;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Widgets\Widget;

class Refilament
{
    /**
     * Schema resolvers keyed by the schema id the client sends with
     * resolve-options requests (docs/CONTRACT.md, "Options").
     *
     * @var array<string, Closure(): Schema>
     */
    protected array $schemaResolvers = [];

    /**
     * Table resolvers keyed by the table id the client requests through the
     * table index endpoint (docs/CONTRACT.md, "Tables").
     *
     * @var array<string, Closure(): Table>
     */
    protected array $tableResolvers = [];

    /**
     * Discovered resource classes keyed by their table id, in discovery
     * order. The package auto-registers the list and create page routes from
     * this map (docs/ARCHITECTURE.md, "Resources").
     *
     * @var array<string, class-string<resource>>
     */
    protected array $resourceClasses = [];

    /**
     * Lazily-built panel config (slice 1.9 — docs/ROADMAP.md "1.9 Panel
     * shell"), assembled from the discovered resources on first access so it
     * reflects any resources registered earlier in the same request.
     */
    protected ?Panel $panel = null;

    /**
     * Discovered relation manager classes, keyed by their parent resource's
     * table id and then by the relationship name they host (slice 1.8). The
     * scoped relation endpoint resolves a manager by these two keys and
     * rebuilds the owner-scoped query from the parent on every request.
     *
     * @var array<string, array<string, class-string<RelationManager>>>
     */
    protected array $relationManagers = [];

    /**
     * Widget resolvers keyed by the widget id the client requests through the
     * typed widget data endpoint (slice 3.2 — docs/CONTRACT.md, "Widgets").
     * The closure rebuilds the widget per request (filters + data closures
     * included), mirroring registerSchemaResolver/registerTable.
     *
     * @var array<string, Closure(): Widget>
     */
    protected array $widgetResolvers = [];

    /**
     * Register the resolver for a schema document, keyed by its id.
     *
     * The closure must return the live schema definition (including any
     * server-side option resolvers) — never a serialized array, since
     * closures cannot survive serialization.
     *
     * @param  Closure(): Schema  $resolver
     */
    public function registerSchemaResolver(string $key, Closure $resolver): static
    {
        $this->schemaResolvers[$key] = $resolver;

        return $this;
    }

    public function resolveSchema(string $key): ?Schema
    {
        $resolver = $this->schemaResolvers[$key] ?? null;

        if (! $resolver instanceof Closure) {
            return null;
        }

        return $resolver();
    }

    /**
     * Register the resolver for a table definition, keyed by its id.
     *
     * @param  Closure(): Table  $resolver
     */
    public function registerTable(string $key, Closure $resolver): static
    {
        $this->tableResolvers[$key] = $resolver;

        return $this;
    }

    public function resolveTable(string $key): ?Table
    {
        $resolver = $this->tableResolvers[$key] ?? null;

        if (! $resolver instanceof Closure) {
            return null;
        }

        return $resolver();
    }

    /**
     * Register the resolver for a widget, keyed by its id (the kebab widget
     * class basename, or a custom key). The closure must return the live
     * widget instance — filters and data closures included, since a widget's
     * data can only re-resolve server-side (never survive serialization).
     *
     * @param  Closure(): Widget  $resolver
     */
    public function registerWidgetResolver(string $key, Closure $resolver): static
    {
        $this->widgetResolvers[$key] = $resolver;

        return $this;
    }

    /**
     * The widget instance registered under a widget id, if any.
     */
    public function resolveWidget(string $key): ?Widget
    {
        $resolver = $this->widgetResolvers[$key] ?? null;

        if (! $resolver instanceof Closure) {
            return null;
        }

        return $resolver();
    }

    /**
     * Register every Resource class found in a directory — including nested
     * folders — under its table and form ids (docs/ARCHITECTURE.md,
     * "Resources"). Mirrors Filament's panel resource discovery; a resource
     * opts out via its isDiscovered(). The namespace is derived from the
     * file's path relative to the scanned root, so a self-contained
     * per-resource folder (`Resources/Posts/PostResource.php` →
     * `App\Refilament\Resources\Posts\PostResource`) resolves its class
     * without manual registration.
     */
    public function registerResourcesFromDirectory(string $path, string $namespace): static
    {
        if (! is_dir($path)) {
            return $this;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Resource.php')) {
                continue;
            }

            // PSR-4 maps the folder structure under the scan root onto the
            // namespace — `Resources/Posts/PostResource.php` gets
            // `App\Refilament\Resources\Posts`.
            $relative = substr($file->getPath(), strlen($path) + 1);
            $class = rtrim($namespace, '\\')
                .'\\'.($relative !== '' ? str_replace(DIRECTORY_SEPARATOR, '\\', $relative).'\\' : '')
                .basename($file->getFilename(), '.php');

            /** @var class-string<resource> $class */
            if (! is_subclass_of($class, Resource::class)) {
                continue;
            }

            $this->registerResources($class);
        }

        return $this;
    }

    /**
     * Register one resource class's table and form under its ids.
     *
     * @param  class-string<resource>  $class
     */
    public function registerResources(string $class): static
    {
        if (! $class::isDiscovered()) {
            return $this;
        }

        $tableId = $class::getTableId();

        if (isset($this->resourceClasses[$tableId])) {
            // Already discovered under this id — skip so the table/form
            // resolvers and the page routes never disagree about which
            // class wins a duplicate id.
            return $this;
        }

        $this->registerTable(
            $tableId,
            static fn (): Table => $class::table(new Table),
        );

        $this->registerSchemaResolver(
            $class::getFormId(),
            static fn (): Schema => $class::form(new Schema),
        );

        $this->resourceClasses[$tableId] = $class;

        // Register every relation manager the resource lists under its table
        // id, keyed by the to-many relationship each hosts (slice 1.8) — the
        // scoped relation endpoint resolves them by name.
        foreach ($class::getRelations() as $relationClass) {
            $this->relationManagers[$tableId][$relationClass::getRelationshipName()] = $relationClass;
        }

        return $this;
    }

    /**
     * The table ids of the discovered resources — the package auto-registers
     * the list and create page routes from them (docs/ARCHITECTURE.md,
     * "Resources").
     *
     * @return array<int, string>
     */
    public function getResourceTableIds(): array
    {
        return array_keys($this->resourceClasses);
    }

    /**
     * Every discovered resource class, in discovery order.
     *
     * @return array<int, class-string<resource>>
     */
    public function getResources(): array
    {
        return array_values($this->resourceClasses);
    }

    /**
     * The panel config served to the frontend shell (slice 1.9). Built on
     * first access from the currently-discovered resources, lazily so it picks
     * up every resource registered during the request's bootstrap.
     */
    public function panel(): Panel
    {
        return $this->panel ??= Panel::make()
            ->resources($this->getResources())
            ->pages((array) config('refilament.panel.pages', []))
            ->discoverPages(
                (string) config('refilament.panel.pages_path'),
                (string) config('refilament.panel.pages_namespace'),
            )
            ->id(config('refilament.panel.id', 'refilament'))
            ->brandName(config('refilament.panel.brand_name', 'Refilament'))
            ->brandLogo(config('refilament.panel.brand_logo'))
            ->topNavigation(config('refilament.panel.top_navigation', false))
            ->dashboardUrl(config('refilament.panel.dashboard_url', '/refilament'))
            ->colors(config('refilament.panel.colors', []))
            ->widgets(config('refilament.panel.widgets', []))
            ->authGuard(config('refilament.panel.auth_guard', 'web'))
            ->loginUrl(config('refilament.panel.login_url'))
            ->authMiddleware(config('refilament.panel.auth_middleware', []));
    }

    /**
     * The resource class registered under a table id, if any.
     *
     * @return class-string<resource>|null
     */
    public function getResourceClass(string $tableId): ?string
    {
        return $this->resourceClasses[$tableId] ?? null;
    }

    /**
     * The current panel user authorization decisions are made for (slice 4.1
     * — docs/ROADMAP.md "4.1 Authorization"). Resolved lazily per request
     * through the panel's auth guard, so it always reflects the actual
     * visitor (there is no persistent component between requests to remember
     * state). Resource, Action and BulkAction all delegate here so an
     * ability check for a table action uses the same user as a resource page
     * gate.
     */
    public function authorizationUser(): ?Authenticatable
    {
        $guard = $this->panel()->getAuthGuard();

        $user = app('auth')->guard($guard)->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * The relation manager class registered under a resource's table id and a
     * relationship name, if any (slice 1.8 — docs/CONTRACT.md, "Relations").
     *
     * @return class-string<RelationManager>|null
     */
    public function getRelationManager(string $resourceTableId, string $relationshipName): ?string
    {
        return $this->relationManagers[$resourceTableId][$relationshipName] ?? null;
    }

    /**
     * Every relation manager registered under a resource's table id, keyed by
     * the relationship each hosts (slice 1.8). Used by record pages to know
     * which manager-driven tabs to render under an owner's edit/view form.
     *
     * @return array<string, class-string<RelationManager>>
     */
    public function getRelationManagers(string $resourceTableId): array
    {
        return $this->relationManagers[$resourceTableId] ?? [];
    }

    /**
     * Auto-register one page route per page name in every discovered
     * resource's getPages() map (slice 1.6 — docs/ROADMAP.md "1.6 Page
     * system"). Called from the service provider at boot; also re-runnable
     * so late-registered resources get their page routes.
     *
     * Page names are registered once — every resource shares the built-in
     * index/create/edit/view URI shapes (and Laravel's RouteCollection is
     * keyed by method|uri, so a second registration would silently replace
     * the first), so the first resource to declare a page name wins its
     * path, like Filament's panel-wide page-name uniqueness. A resource
     * declaring the same name under a *different* path is a configuration
     * bug (the shared route is what renders — the second path could never
     * match), so it throws instead of silently shadowing.
     */
    public function registerPageRoutes(): static
    {
        $registeredPageNames = [];
        $registeredPagePaths = [];

        foreach ($this->getResourceTableIds() as $resourceId) {
            $class = $this->getResourceClass($resourceId);

            if ($class === null) {
                continue;
            }

            foreach ($class::getPages() as $pageName => $registration) {
                $path = $registration->getPath();

                if (isset($registeredPageNames[$pageName])) {
                    // Same page name across resources must mean the same path
                    // — a resource declaring a different path under a name
                    // another resource already claimed would be silently
                    // shadowed (the shared route is what renders), so it is a
                    // configuration bug, not a supported override.
                    if ($path !== null && $path !== $registeredPagePaths[$pageName]) {
                        throw new LogicException(
                            "Page [{$pageName}] is registered with conflicting paths "
                            ."[{$registeredPagePaths[$pageName]}] and [{$path}] — resources must agree "
                            .'on the path of a shared page name.',
                        );
                    }

                    continue;
                }

                $registeredPageNames[$pageName] = true;
                $registeredPagePaths[$pageName] = $path;

                $registration->registerRoute("refilament.resource.{$pageName}");
            }
        }

        $pages = $this->panel()->getPages();

        if ($pages !== []) {
            $pageSlugs = [];

            foreach ($pages as $pageClass) {
                $slug = $pageClass::getSlug();

                if (isset($pageSlugs[$slug])) {
                    throw new LogicException(
                        "Standalone pages [{$pageSlugs[$slug]}] and [{$pageClass}] both use the "
                        ."slug [{$slug}] — panel pages must have unique slugs.",
                    );
                }

                $pageSlugs[$slug] = $pageClass;
            }

            // One shared route serves every standalone panel page — the
            // where() gate restricts it to the declared slugs, mirroring the
            // shared {resource} route for resource pages. Registered here
            // (after the resource routes above) so it can't shadow the
            // exact-path dashboard or collide with a discovered resource id.
            RouteFacade::get('/refilament/{page}', [PanelPageController::class, 'show'])
                ->where('page', implode('|', array_map('preg_quote', array_keys($pageSlugs))))
                ->middleware([PanelAuthenticate::class])
                ->name('refilament.page');
        }

        return $this;
    }

    /**
     * The standalone panel page whose slug matches a given
     * `{page}` route segment, or null if none. Used by PanelPageController
     * to resolve the page class from the URL — the panel auto-registers a
     * single shared route gated to the slugs of every standalone page, so
     * the lookup is the inverse of that gate.
     *
     * @return class-string<Page>|null
     */
    public function resolvePanelPage(string $slug): ?string
    {
        foreach ($this->panel()->getPages() as $pageClass) {
            if ($pageClass::getSlug() === $slug) {
                return $pageClass;
            }
        }

        return null;
    }
}
