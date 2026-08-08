<?php

declare(strict_types=1);

namespace Refilament\Refilament\GlobalSearch\Providers\Contracts;

use Refilament\Refilament\GlobalSearch\GlobalSearchResults;

/**
 * A global search implementation (slice 3.5) — mirrors
 * filament-source/panels/src/GlobalSearch/Providers/Contracts/GlobalSearchProvider.php.
 *
 * Given a query, returns the grouped results across whatever the provider
 * knows how to search. The default provider aggregates the panel's resources.
 */
interface GlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults;
}
