<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Illuminate\Support\Arr;

/**
 * Base class for layout components (grid, section, ...).
 *
 * Layouts wrap child components and serialize them as nested `schema` nodes
 * in the JSON contract (docs/CONTRACT.md). They mirror Filament's container
 * naming (`schema()` for children, `getChildComponents()` for access) where
 * that naming is pure data.
 */
abstract class Layout extends Component
{
    /** @var array<int, Component> */
    protected array $childComponents = [];

    /**
     * @param  array<int, Component>|Component  $components
     */
    public function schema(array|Component $components): static
    {
        $this->childComponents = array_merge($this->childComponents, Arr::wrap($components));

        return $this;
    }

    /**
     * @return array<int, Component>
     */
    public function getChildComponents(): array
    {
        return $this->childComponents;
    }

    /**
     * Recursively find a component by name within this layout's children.
     */
    public function findComponentByName(string $name): ?Component
    {
        foreach ($this->getAllChildComponents() as $component) {
            if ($component->getName() === $name) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Every descendant component in this layout's tree, depth-first.
     *
     * @return array<int, Component>
     */
    public function getAllChildComponents(): array
    {
        $components = [];

        foreach ($this->childComponents as $component) {
            $components[] = $component;

            if ($component instanceof Layout) {
                array_push($components, ...$component->getAllChildComponents());
            }
        }

        return $components;
    }

    /**
     * Serialize the layout's children to contract nodes.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeChildren(): array
    {
        return array_map(
            static fn (Component $component): array => $component->toArray(),
            $this->childComponents,
        );
    }
}
