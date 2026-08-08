<?php

declare(strict_types=1);

namespace Refilament\Refilament\Pages;

use Illuminate\Support\Str;
use Refilament\Refilament\Refilament;

/**
 * Base page (slice 1.6 — docs/ROADMAP.md "1.6 Page system").
 *
 * A page knows its Inertia component and how to build its payload. Resource
 * pages (the built-in list/create/edit/view plus custom pages) extend
 * Resources\Pages\Page, which adds the resource linkage and the route()
 * registration factory; standalone pages extend this base and are wired by
 * the panel shell (slice 1.9 ->pages([...])) — those register their route via
 * getRoutePath()/getInertiaComponent() and are served by the single
 * PanelPageController. Mirrors Filament's Page surface where it is pure data —
 * navigation metadata feeds the panel shell, never the page payload.
 */
abstract class Page
{
    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?int $navigationSort = null;

    protected static ?string $navigationGroup = null;

    /**
     * The URL path this page registers under, relative to its resource segment
     * (resource pages) or the panel root (standalone pages). Resource pages'
     * route() factory uses it; standalone panel pages are served at their
     * getSlug() instead, so this defaults empty.
     */
    protected static string $routePath = '';

    /**
     * Whether this page appears in the panel sidebar. Only pages that opt in
     * (and are not a built-in resource page already surfaced by their
     * resource's nav item) register a nav entry — mirroring Filament, where
     * most pages do not appear in the sidebar. Resource pages default to
     * false; custom pages set it to true to surface themselves.
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function getTitle(): string
    {
        return static::$title ?? static::getNavigationLabel();
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel
            ?? static::$title
            ?? Str::headline(class_basename(static::class));
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }

    /**
     * The URL slug for this standalone panel page (slice 1.9 ->pages()),
     * relative to the panel root. Defaults to the kebab-cased plural of the
     * class basename, mirroring Filament's `getDefaultSlug()`, e.g. an
     * `AboutPage` class serves `/refilament/about-page`. The panel reads it
     * when auto-registering the page's route and its sidebar item.
     */
    public static function getSlug(): string
    {
        return (string) Str::slug(Str::kebab(class_basename(static::class)));
    }

    /**
     * The Inertia props for this standalone page (slice 1.9 ->pages()),
     * computed server-side per request. Standalone pages override this to add
     * their own data. Named distinctly from the resource pages' getViewData()
     * so the two page families can coexist on this shared base — the panel
     * page controller renders getInertiaComponent() with these props.
     *
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [];
    }

    /**
     * The Inertia page component this page renders — resolved through the
     * app's ./pages glob (docs/CONTRACT.md, "Pages").
     */
    abstract public static function getInertiaComponent(): string;
}
