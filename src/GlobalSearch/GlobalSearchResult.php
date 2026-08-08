<?php

declare(strict_types=1);

namespace Refilament\Refilament\GlobalSearch;

/**
 * One match in the panel's global search results (slice 3.5) — mirrors
 * filament-source/panels/src/GlobalSearch/GlobalSearchResult.php.
 *
 * A pure value object: the headline + where it links + optional detail lines.
 * There is no server-side action surface here — actions on search results are
 * deferred (they assume a Livewire-aware record context; our record pages are
 * the navigation target).
 */
class GlobalSearchResult
{
    /**
     * @param  array<string, string>  $details  label => value pairs
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly array $details = [],
    ) {}

    /**
     * @return array{title: string, url: string, details: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'details' => $this->details,
        ];
    }
}