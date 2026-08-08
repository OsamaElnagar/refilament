<?php

declare(strict_types=1);

namespace Refilament\Refilament\Pages;

use Illuminate\Support\Str;

/**
 * Base page (slice 1.6 — docs/ROADMAP.md "1.6 Page system").
 *
 * A page knows its Inertia component and how to build its payload. Resource
 * pages (the built-in list/create/edit/view plus custom pages) extend
 * Resources\Pages\Page, which adds the resource linkage and the route()
 * registration factory; standalone pages extend this base and are wired by
 * the app (the slice 1.9 panel shell will register them like Filament's
 * ->pages([...])). Mirrors Filament's Page surface where it is pure data —
 * navigation metadata feeds the panel shell later, never the page payload.
 */
abstract class Page
{
    /**
     * The route path this page serves under, relative to its resource for
     * resource pages. Informational in v1 — built-in pages derive their
     * route from the getPages() registration.
     */
    protected static string $routePath = '/';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?int $navigationSort = null;

    protected static ?string $navigationGroup = null;

    /**
     * Whether this page appears in the panel sidebar. Only pages that opt in
     * (and are not a built-in resource page already surfaced by their
     * resource's nav item) register a nav entry — mirroring Filament, where
     * most pages do not appear in the sidebar. Resource pages default to
     * false; custom pages set it to true to surface themselves.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isDiscovered = true;

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }

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

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }

    public static function isDiscovered(): bool
    {
        return static::$isDiscovered;
    }

    /**
     * The Inertia page component this page renders — resolved through the
     * app's ./pages glob (docs/CONTRACT.md, "Pages").
     */
    abstract public static function getInertiaComponent(): string;
}
