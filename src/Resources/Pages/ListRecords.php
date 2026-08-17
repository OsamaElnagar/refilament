<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Actions\CreateAction;
use Refilament\Refilament\Refilament;

/**
 * The built-in list page for a resource (slice 1.6) — the default 'index'
 * slot in Resource::getPages(). Renders the generic refilament/resource-table
 * Inertia page with the resource's table payload (definition + first page of
 * rows) plus the page-only resourceTitle prop. This is the canonical
 * pages-as-table: a page whose payload IS a table payload.
 */
class ListRecords extends Page
{
    /**
     * The page's own crumb in breadcrumbs (slice 1.11) — "List", matching
     * Filament's list-records breadcrumb default. The resource crumb (the
     * list page itself) leads it.
     */
    protected static ?string $breadcrumb = 'List';

    public static function getInertiaComponent(): string
    {
        return 'refilament/resource-table';
    }

    /**
     * The default header actions for a resource's list page (slice 1.10):
     * the CreateAction — mirroring Filament, where the generated list page
     * ships `getHeaderActions()` returning `CreateAction::make()`. Ours
     * defaults at the base so every resource gets the button even without a
     * generated page class; a generated (or hand-written) list page overrides
     * this method to add more actions beside it. The page serializer resolves
     * the label, the create-page URL (or modal fallback) and the create
     * authorization gate at request time.
     *
     * @return array<int, Action>
     */
    protected static function getHeaderActions(string $resource): array
    {
        return [CreateAction::make()];
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        static::authorizeViewAny($resource);

        $table = $refilament->resolveTable($resource);

        // Unreachable in normal runs — the route's where() constraint only
        // admits ids discovered at boot. It guards stale route caches: a
        // resource removed after route:cache still matches the baked regex
        // but no longer resolves, so without this the render would crash.
        if ($table === null) {
            abort(404);
        }

        // Merge order is deliberate: the page-level props (resource,
        // resourceTitle, view data) spread last so they are authoritative
        // over any table payload key of the same name.
        return [
            ...$table->toPayload(),
            ...parent::getPayload($resource, $refilament),
        ];
    }
}
