<?php

declare(strict_types=1);

namespace Refilament\Refilament\GlobalSearch\Providers;

use Refilament\Refilament\GlobalSearch\GlobalSearchResults;
use Refilament\Refilament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Refilament\Refilament\Refilament;

/**
 * The default global search provider (slice 3.5) — mirrors
 * filament-source/panels/src/GlobalSearch/Providers/DefaultGlobalSearchProvider.php.
 *
 * Aggregates the panel's discovered resources: those that can globally search
 * contribute a category (keyed by their plural model label) holding the
 * records matching the query. Resources are visited in global-search sort
 * order, so category ordering is deterministic.
 */
class DefaultGlobalSearchProvider implements GlobalSearchProvider
{
    public function __construct(protected Refilament $refilament) {}

    public function getResults(string $query): ?GlobalSearchResults
    {
        if (trim($query) === '') {
            return null;
        }

        $builder = GlobalSearchResults::make();

        $resources = $this->refilament->getResources();

        usort(
            $resources,
            fn (string $a, string $b): int => ($a::getGlobalSearchSort() ?? 0) <=> ($b::getGlobalSearchSort() ?? 0),
        );

        foreach ($resources as $resource) {
            if (! $resource::canGloballySearch()) {
                continue;
            }

            $hits = $resource::getGlobalSearchResults($query);

            if ($hits->isEmpty()) {
                continue;
            }

            $builder->category($resource::getPluralModelLabel(), $hits->all());
        }

        return $builder;
    }
}
