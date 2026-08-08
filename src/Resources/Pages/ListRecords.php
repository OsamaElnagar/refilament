<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

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
    public static function getInertiaComponent(): string
    {
        return 'refilament/resource-table';
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
