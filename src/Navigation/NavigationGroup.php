<?php

declare(strict_types=1);

namespace Refilament\Refilament\Navigation;

/**
 * A labelled grouping of sidebar items (slice 1.9 — docs/ROADMAP.md "1.9 Panel
 * shell"), mirroring the pure-data surface of
 * `filament-source/panels/src/Navigation/NavigationGroup.php`. Items whose
 * `group()` matches this group's label are listed beneath it; groups render a
 * heading in the sidebar. `collapsible()`/`collapsed()` are configuration the
 * client may honor; the collapsed-group UI itself is deferred.
 */
class NavigationGroup
{
    protected string $label;

    protected bool $shouldTranslateLabel = false;

    protected ?string $icon = null;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    /**
     * @var array<int, NavigationItem>
     */
    protected array $items = [];

    final public function __construct(string $label)
    {
        $this->label($label);
    }

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Treat the group label as a translation key resolved through the app's
     * translator when the navigation group is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function collapsed(bool $condition = true): static
    {
        $this->collapsed = $condition;
        $this->collapsible($condition);

        return $this;
    }

    /**
     * @param  array<int, NavigationItem>  $items
     */
    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->shouldTranslateLabel ? __($this->label) : $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    /**
     * @return array<int, NavigationItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->getLabel(),
            ...($this->icon !== null ? ['icon' => $this->getIcon()] : []),
            ...($this->collapsible ? ['collapsible' => true] : []),
            ...($this->collapsed ? ['collapsed' => true] : []),
            'items' => array_map(
                static fn (NavigationItem $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
