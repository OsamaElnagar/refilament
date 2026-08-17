<?php

declare(strict_types=1);

namespace Refilament\Refilament\Clusters;

use Illuminate\Support\Str;
use LogicException;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;

/**
 * A page cluster (mirrors `Filament\Clusters\Cluster`) — a page-like class
 * that groups related pages and resources under one sidebar entry with its
 * own label, icon and sort (the canonical example: a Settings cluster
 * holding the profile and settings pages). Components opt in by declaring
 * `protected static ?string $cluster = SomeCluster::class;`.
 *
 * A cluster is itself a page (it inherits the page navigation surface), but
 * it never renders: hitting its URL redirects to the first accessible
 * clustered component (Filament's `mount()` does the same). The sidebar
 * serializes it as a parent item whose children are the clustered
 * components' own nav items (sub-navigation), and every clustered page's
 * breadcrumbs gain the cluster crumb, linked to the cluster URL.
 */
class Cluster extends Page
{
    /**
     * The crumb this cluster shows in its members' breadcrumbs (mirrors
     * Filament's `$clusterBreadcrumb`). Defaults to the navigation label.
     */
    protected static ?string $clusterBreadcrumb = null;

    /**
     * Whether the cluster renders its members as sub-navigation under its
     * sidebar item (mirrors Filament's `$shouldRegisterSubNavigation`).
     */
    protected static bool $shouldRegisterSubNavigation = true;

    /**
     * Clusters opt into the sidebar by default — a cluster that groups
     * nothing (or nothing the current user may access) hides itself.
     */
    protected static bool $shouldRegisterNavigation = true;

    /**
     * The pages and resources that declared this cluster as their
     * `$cluster`, in registration order.
     *
     * @return array<int, class-string>
     */
    public static function getClusteredComponents(): array
    {
        return app(Refilament::class)->getClusteredComponents(static::class);
    }

    /**
     * Whether any clustered component is accessible to the current user —
     * a cluster the user can't reach any part of hides from the sidebar
     * (mirrors Filament's `canAccessClusteredComponents()`).
     */
    public static function canAccessClusteredComponents(): bool
    {
        foreach (static::getClusteredComponents() as $component) {
            if (is_subclass_of($component, \Refilament\Refilament\Resources\Resource::class)) {
                if ($component::canAccess()) {
                    return true;
                }

                continue;
            }

            if (method_exists($component, 'canAccess') && $component::canAccess()) {
                return true;
            }
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && static::canAccessClusteredComponents();
    }

    public static function shouldRegisterSubNavigation(): bool
    {
        return static::$shouldRegisterSubNavigation;
    }

    /**
     * The cluster's sidebar label — defaults to the class basename minus
     * the "Cluster" suffix, headline-cased ("SettingsCluster" → "Settings"),
     * mirroring Filament's default.
     */
    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel
            ?? static::$title
            ?? Str::headline(Str::beforeLast(class_basename(static::class), 'Cluster'));
    }

    /**
     * The crumb this cluster shows in its members' breadcrumbs — the
     * `$clusterBreadcrumb` override, else the cluster title, else the same
     * basename-derived label (mirrors Filament's `getClusterBreadcrumb()`).
     */
    public static function getClusterBreadcrumb(): ?string
    {
        return static::$clusterBreadcrumb
            ?? static::$title
            ?? Str::headline(Str::beforeLast(class_basename(static::class), 'Cluster'));
    }

    /**
     * The cluster's URL segment — the kebab of the class basename minus the
     * "Cluster" suffix ("SettingsCluster" → "settings"), mirroring Filament.
     */
    public static function getSlug(): string
    {
        return (string) Str::slug(Str::kebab(Str::beforeLast(class_basename(static::class), 'Cluster')));
    }

    /**
     * A cluster never renders — hitting its URL redirects to the first
     * accessible clustered component (its redirect route), so this is never
     * invoked.
     */
    public static function getInertiaComponent(): string
    {
        throw new LogicException('Cluster ['.static::class.'] is a navigation container and never renders a page.');
    }
}
