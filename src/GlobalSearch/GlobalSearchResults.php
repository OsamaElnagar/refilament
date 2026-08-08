<?php

declare(strict_types=1);

namespace Refilament\Refilament\GlobalSearch;

use LogicException;

/**
 * The aggregate global search results, grouped into named categories (slice
 * 3.5) — mirrors filament-source/panels/src/GlobalSearch/GlobalSearchResults.php.
 *
 * One category per contributing resource (its plural model label), each holding
 * the matched GlobalSearchResult objects. Order follows the resources' global
 * search sort at the provider layer, not here.
 */
class GlobalSearchResults
{
    /**
     * @var array<string, array<int, GlobalSearchResult>>
     */
    protected array $categories = [];

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  array<int, GlobalSearchResult>  $results
     */
    public function category(string $name, array $results = []): static
    {
        if (isset($this->categories[$name])) {
            throw new LogicException("Duplicate global search category [{$name}].");
        }

        $this->categories[$name] = $results;

        return $this;
    }

    /**
     * @return array<string, array<int, GlobalSearchResult>>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return bool true when at least one category holds an empty result list
     */
    public function hasEmptyCategories(): bool
    {
        foreach ($this->categories as $results) {
            if ($results === []) {
                return true;
            }
        }

        return false;
    }
}
