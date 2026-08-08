<?php

declare(strict_types=1);

namespace Refilament\Refilament\Panel;

use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Widgets\Widget;

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

    protected string $brandName = 'Refilament';

    protected bool $sidebarCollapsible = false;

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
     * @var array<int, class-string<Widget>>
     */
    protected array $widgets = [];

    protected string $dashboardUrl = '/refilament';

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

    public function brandName(string $brandName): static
    {
        $this->brandName = $brandName;

        return $this;
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
     * @param  array<int, class-string<Widget>>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    public function dashboardUrl(string $url): static
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

    public function getId(): string
    {
        return $this->id;
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
        return $this->dashboardUrl;
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
        $items = array_merge($this->resourceNavigationItems(), $this->navigationItems);

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

        return [
            'id' => $this->id,
            'brandName' => $this->brandName,
            'sidebarCollapsible' => $this->sidebarCollapsible,
            'dashboardUrl' => $this->dashboardUrl,
            ...($this->colors !== [] ? ['colors' => $this->colors] : []),
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
                    ->url('/refilament/'.$resource::getTableId().$page::getRoutePath())
                    ->group($page::getNavigationGroup() ?? $resource::getNavigationGroup())
                    ->sort($page::getNavigationSort())
                    ->icon($page::getNavigationIcon() ?? $resource::getNavigationIcon());
            }
        }

        return $items;
    }
}
