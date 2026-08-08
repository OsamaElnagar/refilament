<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use LogicException;

/**
 * Tabs layout (slice 2.6).
 *
 * A horizontal tab bar driven by `Tab` children (mirrors Filament's `Tabs`,
 * where the container itself is "tabs" and each child is a `Tab`). The active
 * tab is 1-indexed and defaults to 1; it is pure client state — clicking a
 * tab never hits the server. Deferred: vertical/scrollable variants,
 * `persistTabInQueryString`, badges, icons and render hooks — client or
 * Livewire machinery no form needs yet.
 */
class Tabs extends Layout
{
    /** @var array<int, Tab> */
    protected array $childComponents = [];

    protected int $activeTab = 1;

    public function getType(): string
    {
        return 'tabs';
    }

    /**
     * @param  array<int, Component>|Component  $tabs
     */
    public function tabs(array|Component $tabs): static
    {
        foreach (is_array($tabs) ? $tabs : [$tabs] as $tab) {
            if (! $tab instanceof Tab) {
                throw new LogicException('Tabs children must be instances of ['.Tab::class.'].');
            }

            $this->childComponents[] = $tab;
        }

        return $this;
    }

    public function activeTab(int $activeTab): static
    {
        $this->activeTab = max($activeTab, 1);

        return $this;
    }

    public function getActiveTab(): int
    {
        return $this->activeTab;
    }

    /**
     * @return array<int, Tab>
     */
    public function getChildComponents(): array
    {
        return $this->childComponents;
    }

    public function toArray(): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'activeTab' => $this->activeTab > 1 ? $this->activeTab : null,
            'schema' => $this->serializeChildren(),
        ]);
    }
}
