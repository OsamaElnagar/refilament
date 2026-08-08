<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use LogicException;
use Refilament\Refilament\Http\Controllers\ResourcePageController;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Pages\Page as BasePage;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Resource;

/**
 * Resource page base (slice 1.6 — docs/ROADMAP.md "1.6 Page system").
 *
 * The base for every page a resource declares in its getPages() map. The
 * built-in list/create/edit/view pages extend this class; custom resource
 * pages do too, declaring their resource and adding their own extra props
 * via getViewData(). Payloads are built server-side per request — the page
 * knows its Inertia component and its props, and the package registers one
 * route per getPages() entry at boot.
 */
abstract class Page extends BasePage
{
    /**
     * The resource this page belongs to. The built-in pages are resolved
     * from the URL resource segment at request time, so this stays null for
     * them; custom resource pages declare it (docs/ARCHITECTURE.md).
     *
     * @var class-string<resource>|null
     */
    protected static ?string $resource = null;

    /**
     * The resource class this page belongs to (custom pages declare it via
     * the $resource property).
     *
     * @return class-string<resource>
     */
    public static function getResource(): string
    {
        if (static::$resource === null) {
            throw new LogicException('Page ['.static::class.'] must declare a [$resource] property.');
        }

        return static::$resource;
    }

    /**
     * Register this page under a route path relative to the resource segment
     * ('/' for the list, '/create', '/{record}/edit', or a custom slug like
     * '/stats'). Mirrors Filament's Page::route(): the returned registration
     * is what Resource::getPages() returns, and the package registers the
     * route at boot — once per page name (all resources share the built-in
     * URI shapes, and the route collection keys by method|uri, so the first
     * resource to declare a page name wins its path). The route is
     * where()-gated to the ids discovered at boot (never an unconstrained
     * catch-all, so app-owned routes like /refilament/playground are not
     * shadowed) and its {record} segment is constrained to [0-9]+ so page
     * slugs never collide with record ids. The {record} constraint does mean
     * record pages assume integer primary keys today (docs/CONTRACT.md,
     * "Pages") — the where() gate itself is built from the ids discovered at
     * boot, which is non-empty exactly when this closure runs (the provider
     * only registers routes for discovered resources).
     */
    public static function route(string $path): PageRegistration
    {
        return new PageRegistration(
            page: static::class,
            path: $path,
            // The URI keeps the {resource} placeholder — the where() gate
            // constrains it to the ids discovered at boot, and the record
            // segment to integers, so the shared route shape is the same for
            // every resource and page slug.
            route: static fn (string $routeName): Route => RouteFacade::get(
                '/refilament/{resource}'.$path,
                [ResourcePageController::class, 'show'],
            )
                ->where('resource', implode('|', array_map('preg_quote', app(Refilament::class)->getResourceTableIds())))
                ->where('record', '[0-9]+')
                ->middleware([PanelAuthenticate::class])
                ->name($routeName),
        );
    }

    /**
     * Build the Inertia props for this page. $resource is the URL segment
     * the page was served under (authoritative — custom pages may also
     * declare $resource for navigation); $record is the {record} route
     * param on record pages.
     *
     * @return array<string, mixed>
     */
    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        return [
            'resource' => $resource,
            'resourceTitle' => static::getResourceTitle($resource),
            ...static::getViewData($resource),
        ];
    }

    /**
     * Extra Inertia props merged into the page payload — a page's own data,
     * computed server-side per request. The pages-as-tables idiom: a page
     * whose payload is mostly its own computed values (reports, dashboards)
     * overrides this; a page that hosts a table overrides getPayload() to
     * merge the table payload instead (the built-in ListRecords is the
     * canonical example).
     *
     * @return array<string, mixed>
     */
    public static function getViewData(string $resource): array
    {
        return [];
    }

    /**
     * The query the {record} route param resolves through — record pages
     * (edit/view) load their model through this so the binding honors the
     * resource's own scoping. Override for soft-delete-aware binding
     * (withTrashed()), mirroring Filament's
     * getRecordRouteBindingEloquentQuery().
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(string $resource): Builder
    {
        $class = app(Refilament::class)->getResourceClass($resource);

        if ($class === null) {
            abort(404);
        }

        $model = $class::getModel();

        return $model::query();
    }

    /**
     * Abort with 403 unless the current user may browse this resource's
     * records (slice 4.1 — docs/ROADMAP.md "4.1 Authorization"). Resolves the
     * class from the URL segment and delegates to Resource::authorizeViewAny().
     */
    protected static function authorizeViewAny(string $resource): void
    {
        $class = app(Refilament::class)->getResourceClass($resource);

        if ($class !== null) {
            $class::authorizeViewAny();
        }
    }

    /**
     * Abort with 403 unless the current user may create a record (slice 4.1).
     */
    protected static function authorizeCreate(string $resource): void
    {
        $class = app(Refilament::class)->getResourceClass($resource);

        if ($class !== null) {
            $class::authorizeCreate();
        }
    }

    /**
     * Abort with 403 unless the current user may view the given record
     * (slice 4.1). The record comes from the resource's own scoped query.
     */
    protected static function authorizeView(string $resource, Model $record): void
    {
        $class = app(Refilament::class)->getResourceClass($resource);

        if ($class !== null) {
            $class::authorizeView($record);
        }
    }

    /**
     * Abort with 403 unless the current user may edit the given record
     * (slice 4.1).
     */
    protected static function authorizeEdit(string $resource, Model $record): void
    {
        $class = app(Refilament::class)->getResourceClass($resource);

        if ($class !== null) {
            $class::authorizeEdit($record);
        }
    }

    /**
     * The display name for a resource's records, derived from its model
     * (e.g. "User") — shared by the list, create, edit and view pages.
     *
     * @param  class-string<resource>  $class
     */
    protected static function resourceTitleFor(string $class): string
    {
        return Str::headline(class_basename($class::getModel()));
    }

    protected static function getResourceTitle(string $resource): string
    {
        $class = app(Refilament::class)->getResourceClass($resource);

        return $class === null ? Str::headline($resource) : static::resourceTitleFor($class);
    }
}
