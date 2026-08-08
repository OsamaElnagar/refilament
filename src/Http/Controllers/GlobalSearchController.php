<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Refilament\Refilament\GlobalSearch\GlobalSearchResult;
use Refilament\Refilament\GlobalSearch\GlobalSearchResults;
use Refilament\Refilament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;

/**
 * Serve the panel's global search results as JSON (slice 3.5 —
 * docs/CONTRACT.md, "Global search"). The React runtime debounces the search
 * and fetches this endpoint on each settled term.
 *
 * Query param: q (the term). Empty, whitespace-only or absent terms return a
 * "no results" payload, never an error.
 */
class GlobalSearchController
{
    public function __invoke(Request $request, GlobalSearchProvider $provider): JsonResponse
    {
        $query = (string) $request->query('q', '');

        $results = $provider->getResults($query);

        return response()->json([
            'query' => $query,
            'categories' => $this->serializeCategories($results),
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function serializeCategories(?GlobalSearchResults $results): array
    {
        if ($results === null) {
            return [];
        }

        $serialized = [];

        foreach ($results->getCategories() as $name => $hits) {
            $serialized[$name] = array_map(
                static fn (GlobalSearchResult $result): array => $result->toArray(),
                $hits,
            );
        }

        return $serialized;
    }
}
