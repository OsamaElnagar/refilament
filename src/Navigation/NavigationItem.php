<?php

declare(strict_types=1);

namespace Refilament\Refilament\Navigation;

/**
 * One sidebar navigation entry (slice 1.9 — docs/ROADMAP.md "1.9 Panel shell").
 *
 * Mirrors the pure-data surface of
 * `filament-source/panels/src/Navigation/NavigationItem.php`: a label, a link,
 * an optional icon (an arbitrary string key the client maps to a Lucide icon),
 * an optional grouping and sort, and a badge. Unlike Filament there are no
 * closures — this is config data serialized to the frontend, where the active
 * state is derived from the current URL on the client (no server component
 * state survives to test it).
 */
class NavigationItem
{
    protected ?string $key = null;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected ?string $group = null;

    protected ?string $icon = null;

    protected ?int $sort = null;

    protected ?string $url = null;

    protected null|int|string $badge = null;

    protected bool $openUrlInNewTab = false;

    /**
     * @var array<int, NavigationItem>
     */
    protected array $childItems = [];

    final public function __construct(?string $label = null)
    {
        if (filled($label)) {
            $this->label($label);
        }
    }

    public static function make(?string $label = null): static
    {
        return new static($label);
    }

    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function group(?string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Treat the navigation label as a translation key resolved through the
     * app's translator when the navigation item is serialized. Mirrors
     * Filament's `translateLabel()`; off by default so labels pass through
     * verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

        return $this;
    }

    public function badge(null|int|string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function sort(?int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function url(string $url, bool $openUrlInNewTab = false): static
    {
        $this->url = $url;
        $this->openUrlInNewTab = $openUrlInNewTab;

        return $this;
    }

    /**
     * @param  array<int, NavigationItem>  $childItems
     */
    public function childItems(array $childItems): static
    {
        $this->childItems = $childItems;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key ?? $this->label ?? '';
    }

    public function getLabel(): string
    {
        $label = $this->label ?? '';

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getSort(): int
    {
        return $this->sort ?? -1;
    }

    public function getUrl(): string
    {
        return $this->url ?? '#';
    }

    public function getBadge(): null|int|string
    {
        return $this->badge;
    }

    public function shouldOpenUrlInNewTab(): bool
    {
        return $this->openUrlInNewTab;
    }

    /**
     * @return array<int, NavigationItem>
     */
    public function getChildItems(): array
    {
        return $this->childItems;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->getKey(),
            'label' => $this->getLabel(),
            'url' => $this->getUrl(),
            ...($this->icon !== null ? ['icon' => $this->getIcon()] : []),
            ...($this->badge !== null ? ['badge' => $this->getBadge()] : []),
            ...($this->openUrlInNewTab ? ['openInNewTab' => true] : []),
            'children' => array_map(
                static fn (NavigationItem $item): array => $item->toArray(),
                $this->childItems,
            ),
        ];
    }
}
