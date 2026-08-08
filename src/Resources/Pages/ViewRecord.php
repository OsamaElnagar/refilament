<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources\Pages;

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Column;

/**
 * The built-in read-only view page for a resource (slice 1.6, footer
 * summaries 1.7, infolists 3.3) — the default 'view' slot in
 * Resource::getPages(), served at /refilament/{resource}/{record} with the
 * {record} segment constrained to [0-9]+.
 *
 * The resource's `infolist()` (slice 3.3) drives the page when it declares
 * entries: the schema is bound to the viewed record (`Schema::record()`) and
 * serialized to a `schema` prop the read-only renderer displays. When a
 * resource defines no infolist, the page falls back to the table's column
 * definitions + the record's values + the dataset-wide footer summaries.
 */
class ViewRecord extends Page
{
    public static function getInertiaComponent(): string
    {
        return 'refilament/resource-view';
    }

    public static function getPayload(string $resource, Refilament $refilament, ?string $record = null): array
    {
        $model = static::getRecordRouteBindingEloquentQuery($resource)->findOrFail((string) $record);

        $table = $refilament->resolveTable($resource);

        $resourceClass = $refilament->getResourceClass($resource);

        $infolist = $resourceClass !== null
            ? $resourceClass::infolist(new Schema)->record($model)
            : new Schema;

        // Merge order is deliberate: the page-level props (resource,
        // resourceTitle, view data) spread last so they are authoritative
        // over any column/values key of the same name.
        $payload = [
            'record' => $model->getKey(),
            ...parent::getPayload($resource, $refilament),
        ];

        // A resource that declares infolist entries (slice 3.3) drives the
        // page through its read-only schema — the tailored read-out wins.
        // Otherwise fall back to the table columns + values + dataset-wide
        // footer summaries (the pre-infolist view page).
        if ($infolist->getComponents() !== []) {
            return $payload + ['schema' => $infolist->toArray()['schema']];
        }

        if ($table !== null) {
            $payload += [
                'columns' => array_map(
                    static fn (Column $column): array => $column->toArray(),
                    $table->getColumns(),
                ),
                'values' => $table->getRecordValues($model),
                // Dataset-wide footer summaries (slice 1.7) — computed over
                // the unfiltered query, so the view page's totals always
                // reflect the whole table.
                ...$table->getFullSummary(),
            ];
        }

        return $payload;
    }
}
