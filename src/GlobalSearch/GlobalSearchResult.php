<?php

declare(strict_types=1);

namespace Refilament\Refilament\GlobalSearch;

use Refilament\Refilament\Actions\Action;

/**
 * One match in the panel's global search results (slice 3.5) — mirrors
 * filament-source/panels/src/GlobalSearch/GlobalSearchResult.php.
 *
 * A pure value object: the headline + where it links + optional detail lines
 * and per-record actions. Closures are never serialized — each action is
 * rebuilt server-side from the resource's `getGlobalSearchResultActions()` and
 * serialized as data (a `url` for navigation, else a named server action the
 * client triggers through the typed global-search action endpoint).
 */
class GlobalSearchResult
{
    /**
     * @param  array<string, string>  $details  label => value pairs
     * @param  array<int, Action>  $actions  per-record actions (slice 3.5)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $resource,
        public readonly mixed $record,
        public readonly array $details = [],
        public readonly array $actions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'title' => $this->title,
            'url' => $this->url,
            'resource' => $this->resource,
            'record' => $this->record,
            'details' => $this->details,
        ];

        if ($this->actions !== []) {
            $payload['actions'] = array_map(
                static fn (Action $action): array => $action->toArray(),
                $this->actions,
            );
        }

        return $payload;
    }
}
