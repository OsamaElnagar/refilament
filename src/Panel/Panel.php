<?php

declare(strict_types=1);

namespace Refilament\Refilament\Panel;

use Closure;
use Illuminate\Filesystem\Filesystem;
use LogicException;
use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Widgets\Widget;
use ReflectionClass;

/**
 * The package's single panel config (slice 1.9 — docs/ROADMAP.md "1.9 Panel
 * shell"), mirroring the config surface of Filament's Panel but describing
 * pure data served to the frontend shell — there is no Livewire component to
 * configure. The panel collects the sidebar navigation: one item per
 * navigation-registered resource (plus one per opt-in custom resource page,
 * plus any module-added items/groups), bucketed into groups by each item's
 * group name.
 *
 * Active nav state is derived on the client from the current URL; nothing here
 * remembers state between requests. `colors` become CSS custom properties the
 * shell applies (primary theming); `widgets` are the classes the dashboard
 * route renders (built per request, so their stat closures never cross the
 * wire). Collapsible-group UI and the auth gate remain later slices of 1.9.
 */
class Panel
{
    final public function __construct() {}

    protected string $id = 'refilament';

    /**
     * The panel's URL prefix — everything the panel serves (the dashboard,
     * every resource page, the standalone pages and the typed endpoints)
     * lives under "/{path}". Mirrors Filament's `Panel::path('admin')`: the
     * first identity decision a consumer makes alongside `id()`. Kept as a
     * bare segment (no slashes) so route registration and every URL built
     * here agree on one shape.
     */
    protected string $path = 'refilament';

    protected string $brandName = 'Refilament';

    /**
     * A brand logo beside the brand name — a URL, or a closure resolving to
     * one (mirrors Heaven's closure `brandLogo()`, minus the Htmlable). The
     * React shell renders it as the sidebar / top-nav mark.
     *
     * @var string|Closure(): string|null
     */
    protected mixed $brandLogo = null;

    protected bool $sidebarCollapsible = false;

    /**
     * Render the navigation in a top bar instead of the sidebar (mirrors
     * Filament's `topNavigation()`), driven by the shell contract.
     */
    protected bool $topNavigation = false;

    /**
     * @var array<int, class-string<resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, NavigationItem>
     */
    protected array $navigationItems = [];

    /**
     * @var array<int, NavigationGroup>
     */
    protected array $navigationGroups = [];

    /**
     * CSS custom-property values keyed by their suffix ('primary' => hex) the
     * shell applies to theme the brand (docs/ROADMAP.md, "1.9 ->colors()").
     *
     * @var array<string, string>
     */
    protected array $colors = [];

    /**
     * Extension points where the shell renders consumer-provided UI (slice
     * B1) — mirrors Filament's `renderHook(PanelsRenderHook::...)`, translated
     * for a React shell. Each entry names a shell slot
     * ('sidebar-footer' | 'topbar-end' | 'page-start') and a client-side
     * component key the app maps to a React component (registered with
     * `registerShellSlot`). Declaring a hook here is what arms it: the shell
     * only renders slots the server has enabled.
     *
     * @var array<string, string>
     */
    protected array $renderHooks = [];

    /**
     * @var array<int, class-string<Widget>>
     */
    protected array $widgets = [];

    /**
     * Standalone panel pages — slices of behavior not tied to a resource,
     * e.g. a settings or about page — that belong to this panel
     * (docs/ROADMAP.md, "1.9 ->pages([...])"). They extend Pages\Page and
     * are served by the shared PanelPageController route; opt-in pages that
     * set shouldRegisterNavigation() also surface in the sidebar.
     *
     * @var array<int, class-string<Page>>
     */
    protected array $pages = [];

    /**
     * The brand's target URL (the dashboard). Null derives from the panel's
     * `path` — the default, so changing the path moves the brand link with
     * it; an explicit value always wins.
     */
    protected ?string $dashboardUrl = null;

    /**
     * Middleware applied to every panel route (the shell pages and the typed
     * endpoints) — mirrors Filament's `Panel::middleware()`. Defaults to an
     * empty list; the framework's `web` group (sessions + CSRF +
     * SubstituteBindings) is always applied around the panel routes, and this
     * list runs after it — a consumer adds e.g. `RateLimiter::class` or their
     * own middleware here. Pure config resolved at route registration, never
     * serialized across the wire.
     *
     * @var array<int, class-string|string>
     */
    protected array $middleware = [];

    /**
     * Whether the shell renders the database-notifications bell (slice B3),
     * mirroring Filament's `Panel::databaseNotifications()`. The bell polls
     * the typed notifications endpoint for the unread count and latest rows,
     * and marks notifications read as the user dismisses them.
     */
    protected bool $databaseNotifications = false;

    /**
     * The bell's polling interval, Filament's '7s' / '150s' style. Defaults to
     * '30s' when notifications are enabled without an explicit interval.
     */
    protected ?string $notificationsPolling = null;

    /**
     * The auth guard the panel's access gate checks (slice 1.9 "auth gate").
     * Mirrors Filament's `Panel::authGuard()` — the guard the panel's
     * `Authenticate` middleware authenticates against before rendering any
     * shell page. Pure config: which guard to check is decided by the app,
     * not the request.
     */
    protected string $authGuard = 'web';

    /**
     * Where an unauthenticated visitor is redirected when the panel's access
     * gate is enabled (slice 1.9 "auth gate"). `null` (the default) keeps the
     * gate permissive — no login URL, no redirection, the workbench stays open.
     * When set alongside a configured auth middleware, an unauthenticated
     * request to a shell page is redirected here.
     */
    protected ?string $loginUrl = null;

    /**
     * Middleware applied to the panel's shell-rendering routes (the dashboard
     * and every resource page) — slice 1.9 "auth gate", mirroring Filament's
     * `Panel::authMiddleware()`. Defaults to an empty list, so the panel serves
     * every shell page openly; registering `Authenticate::class` here (plus a
     * `loginUrl()`) turns the gate on. The list is serialized into the route
     * definitions at boot, never across the wire.
     *
     * @var array<int, class-string>
     */
    protected array $authMiddleware = [];

    public static function make(): static
    {
        return new static;
    }

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * The panel's URL prefix, stored bare ('admin', never '/admin').
     */
    public function path(string $path): static
    {
        $this->path = trim($path, '/');

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Build an absolute panel URL for a path relative to the panel's prefix
     * ('/{resource}/create' → '/refilament/{resource}/create'). Every URL the
     * panel or its resources hand to the shell goes through here, so a
     * consumer's `->path('admin')` moves the whole panel.
     */
    public function url(string $path = ''): string
    {
        $url = '/'.ltrim($this->getPath(), '/');

        if ($path !== '') {
            $url .= '/'.ltrim($path, '/');
        }

        return $url;
    }

    public function brandName(string $brandName): static
    {
        $this->brandName = $brandName;

        return $this;
    }

    /**
     * A brand logo rendered beside the brand name. Accepts a URL string or a
     * closure resolving to one (evaluated at serialization, never shipped).
     *
     * @param  string|Closure(): string|null  $logo
     */
    public function brandLogo(string|Closure|null $logo): static
    {
        $this->brandLogo = $logo;

        return $this;
    }

    public function getBrandLogo(): ?string
    {
        $logo = $this->brandLogo;

        if ($logo instanceof Closure) {
            $logo = $logo();
        }

        return is_string($logo) ? $logo : null;
    }

    /**
     * Render the navigation in a top bar instead of the sidebar.
     */
    public function topNavigation(bool $condition = true): static
    {
        $this->topNavigation = $condition;

        return $this;
    }

    public function isTopNavigation(): bool
    {
        return $this->topNavigation;
    }

    /**
     * The "<" method name mirrors Filament's Panel surface.
     */
    public function sidebarCollapsibleOnDesktop(bool $condition = true): static
    {
        $this->sidebarCollapsible = $condition;

        return $this;
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     */
    public function resources(array $resources): static
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * @param  array<int, NavigationItem>  $items
     */
    public function navigationItems(array $items): static
    {
        $this->navigationItems = $items;

        return $this;
    }

    /**
     * @param  array<int, NavigationGroup>  $groups
     */
    public function navigationGroups(array $groups): static
    {
        $this->navigationGroups = $groups;

        return $this;
    }

    /**
     * @param  array<string, string>  $colors  CSS variable suffix => value
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * Arm a shell render hook (slice B1): the named slot renders the given
     * client-side component key wherever the shell places it. Mirrors
     * Filament's `renderHook(PanelsRenderHook::SIDEBAR_FOOTER, ...)`.
     */
    public function renderHook(string $slot, string $component): static
    {
        $this->renderHooks[$slot] = $component;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getRenderHooks(): array
    {
        return $this->renderHooks;
    }

    /**
     * @param  array<int, class-string<Widget>>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    /**
     * Explicitly register standalone panel pages (slice 1.9 "->pages([...])"),
     * mirroring Filament's `Panel::pages()`. Pages are appended to any already
     * registered, deduplicated in getPages().
     *
     * @param  array<int, class-string<Page>>  $pages
     */
    public function pages(array $pages): static
    {
        foreach ($pages as $page) {
            if ($page::getSlug() === '') {
                throw new LogicException("Page [{$page}] must resolve a non-empty slug.");
            }

            $this->pages[] = $page;
        }

        return $this;
    }

    /**
     * Auto-discover standalone panel pages in a directory (slice 1.9),
     * mirroring Filament's `Panel::discoverPages($in, $for)`. Every non-abstract
     * class in `$in` that extends Pages\Page is registered. No-op when the
     * directory doesn't exist, so a documented but not-yet-created folder is
     * not an error.
     */
    public function discoverPages(string $in, string $for): static
    {
        if (! is_dir($in)) {
            return $this;
        }

        $filesystem = app(Filesystem::class);

        $known = array_flip($this->pages);

        foreach ($filesystem->allFiles($in) as $file) {
            $class = $for.'\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (isset($known[$class]) || ! class_exists($class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (! is_subclass_of($class, Page::class)) {
                continue;
            }

            $this->pages[] = $class;
            $known[$class] = true;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<Page>>
     */
    public function getPages(): array
    {
        return array_values(array_unique($this->pages));
    }

    public function dashboardUrl(?string $url): static
    {
        $this->dashboardUrl = $url;

        return $this;
    }

    public function authGuard(string $guard): static
    {
        $this->authGuard = $guard;

        return $this;
    }

    public function loginUrl(?string $url): static
    {
        $this->loginUrl = $url;

        return $this;
    }

    /**
     * @param  array<int, class-string>  $middleware
     */
    public function authMiddleware(array $middleware): static
    {
        $this->authMiddleware = $middleware;

        return $this;
    }

    /**
     * @param  array<int, class-string|string>  $middleware
     */
    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * @return array<int, class-string|string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Enable the shell's database-notifications bell (slice B3) — mirrors
     * Filament's `Panel::databaseNotifications()`. The bell reads the
     * authenticated user's notifications through the typed endpoint.
     */
    public function databaseNotifications(bool $condition = true): static
    {
        $this->databaseNotifications = $condition;

        return $this;
    }

    /**
     * The bell's polling interval, in Filament's '7s' / '150s' style. Falls
     * back to '30s' when unset (Filament's default).
     */
    public function databaseNotificationsPolling(?string $interval): static
    {
        $this->notificationsPolling = $interval;

        return $this;
    }

    public function hasDatabaseNotifications(): bool
    {
        return $this->databaseNotifications;
    }

    public function getNotificationsPolling(): ?string
    {
        return $this->notificationsPolling;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<int, class-string<resource>>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function isSidebarCollapsible(): bool
    {
        return $this->sidebarCollapsible;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * @return array<int, class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    public function getDashboardUrl(): string
    {
        return $this->dashboardUrl ?? $this->url();
    }

    public function getAuthGuard(): string
    {
        return $this->authGuard;
    }

    public function getLoginUrl(): ?string
    {
        return $this->loginUrl;
    }

    /**
     * @return array<int, class-string>
     */
    public function getAuthMiddleware(): array
    {
        return $this->authMiddleware;
    }

    /**
     * Build the sidebar navigation contract: groups (ordered) plus any items
     * that belong to no group. Each registered group keeps its label/icon/
     * collapse configuration; the items assigned to it come from the app nav
     * items whose `group()` matches — a group with no members still renders
     * as a heading, mirroring Filament.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $items = array_merge(
            $this->resourceNavigationItems(),
            $this->panelPageNavigationItems(),
            $this->navigationItems,
        );

        /** @var array<string, NavigationGroup> $groups */
        $groups = [];

        foreach ($this->navigationGroups as $group) {
            $groups[$group->getLabel()] = $group;
        }

        /** @var array<string, array<int, NavigationItem>> $bucketed */
        $bucketed = [];

        /** @var array<int, NavigationItem> $ungrouped */
        $ungrouped = [];

        foreach ($items as $item) {
            $group = $item->getGroup();

            if ($group !== null) {
                $groups[$group] ??= NavigationGroup::make($group);
                $bucketed[$group][] = $item;
            } else {
                $ungrouped[] = $item;
            }
        }

        $groupData = [];

        foreach ($groups as $label => $group) {
            $members = $bucketed[$label] ?? [];
            usort($members, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

            $groupData[] = [
                ...(array) $group->toArray(),
                'items' => array_map(
                    static fn (NavigationItem $item): array => $item->toArray(),
                    $members,
                ),
            ];
        }

        usort($groupData, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);
        usort($ungrouped, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

        $brandLogo = $this->getBrandLogo();

        return [
            'id' => $this->id,
            'path' => $this->getPath(),
            'brandName' => $this->brandName,
            ...($brandLogo !== null ? ['brandLogo' => $brandLogo] : []),
            'sidebarCollapsible' => $this->sidebarCollapsible,
            'topNavigation' => $this->topNavigation,
            'dashboardUrl' => $this->getDashboardUrl(),
            ...($this->colors !== [] ? ['colors' => $this->colors] : []),
            ...($this->renderHooks !== [] ? ['renderHooks' => $this->renderHooks] : []),
            ...($this->databaseNotifications ? ['notifications' => ['polling' => $this->notificationsPolling ?? '30s']] : []),
            'groups' => $groupData,
            'items' => array_map(
                static fn (NavigationItem $item): array => $item->toArray(),
                $ungrouped,
            ),
        ];
    }

    /**
     * One navigation item per navigation-registered resource, pointing at the
     * resource's list page, plus one per opt-in custom resource page (a page
     * in the resource's getPages() map that is not one of the built-in
     * list/create/edit/view pages and whose shouldRegisterNavigation() is
     * true). A custom page inherits the resource's group and icon unless it
     * overrides them.
     *
     * @return array<int, NavigationItem>
     */
    protected function resourceNavigationItems(): array
    {
        $items = [];

        foreach ($this->resources as $resource) {
            if (! $resource::shouldRegisterNavigation()) {
                continue;
            }

            // A resource the current user cannot access (slice 4.1) is hidden
            // from the sidebar — same as Filament, whose nav reflects per-user
            // policy. With no policy the default allows access, so a fresh
            // install lists everything.
            if (! $resource::canAccess()) {
                continue;
            }

            $items[] = NavigationItem::make($resource::getNavigationLabel())
                ->key($resource)
                ->url($resource::getNavigationUrl())
                ->group($resource::getNavigationGroup())
                ->sort($resource::getNavigationSort())
                ->icon($resource::getNavigationIcon());

            foreach ($resource::getPages() as $pageName => $registration) {
                // Only custom pages (not the built-in list/create/edit/view)
                // can register their own nav item — the built-ins are already
                // surfaced by the resource's own nav item above.
                if (in_array($pageName, ['index', 'create', 'edit', 'view'], true)) {
                    continue;
                }

                $page = $registration->getPage();

                if (! $page::shouldRegisterNavigation()) {
                    continue;
                }

                $items[] = NavigationItem::make($page::getNavigationLabel())
                    ->key($page)
                    ->url($this->url('/'.$resource::getTableId().$page::getRoutePath()))
                    ->group($page::getNavigationGroup() ?? $resource::getNavigationGroup())
                    ->sort($page::getNavigationSort())
                    ->icon($page::getNavigationIcon() ?? $resource::getNavigationIcon());
            }
        }

        return $items;
    }

    /**
     * One navigation item per opt-in standalone panel page (slice 1.9
     * "->pages([...])"). A page surfaces in the sidebar only when its
     * shouldRegisterNavigation() is true (the default is false, mirroring
     * Filament, where most pages don't appear). Its URL is the shared
     * page route under the panel's slug.
     *
     * @return array<int, NavigationItem>
     */
    protected function panelPageNavigationItems(): array
    {
        $items = [];

        foreach ($this->getPages() as $page) {
            if (! $page::shouldRegisterNavigation()) {
                continue;
            }

            $items[] = NavigationItem::make($page::getNavigationLabel())
                ->key($page)
                ->url($this->url('/'.$page::getSlug()))
                ->group($page::getNavigationGroup())
                ->sort($page::getNavigationSort())
                ->icon($page::getNavigationIcon());
        }

        return $items;
    }
}
