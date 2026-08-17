<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Closure;
use Illuminate\Support\Arr;

/**
 * Repeater field (mirrors `Filament\Schemas\Components\Repeater`) — a
 * repeatable group of fields: the form value is an array of rows, each row
 * an object keyed by the row schema's field names. Consumers declare the row
 * schema once (`Repeater::make('items')->schema([...])`); the client renders
 * one card per row with add/remove controls and the server validates every
 * row's fields under `items.*.field` (the Schema rule collector descends
 * into repeaters).
 *
 * Pure-data options mirroring Filament: `defaultItems(int)` (rows the form
 * opens with — the initial data builds that many rows from the row fields'
 * defaults), `minItems()` / `maxItems()` (array-count validation),
 * `collapsible()`, `grid(int)` (columns per row), `addActionLabel()`, and
 * `itemLabel()` (a static per-row heading or a `{field}` token template
 * substituted from each row's state — the client evaluates it, so closures
 * stay off the wire).
 *
 * Row-management toggles (all pure data, client-side): `addable()`,
 * `deletable()`, `cloneable()` (duplicate a row), `reorderable()` with
 * `reorderableWithDragAndDrop()` / `reorderableWithButtons()`, `itemNumbers()`
 * (numbered headings), `itemHeaders()` (show/hide the per-row header), and
 * `collapsed()` (start every row folded).
 *
 * Deferred for v1: `relationship()` persistence (server-coupled), `simple()`,
 * `table()` column layout, per-action modification callbacks, and the reactive
 * `afterCreate`/`afterUpdate`/`afterDelete` hooks (no persistent component).
 */
class Repeater extends Component
{
    /** @var array<int, Component> */
    protected array $childComponents = [];

    protected int $defaultItems = 0;

    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    protected ?int $grid = null;

    protected ?string $addActionLabel = null;

    protected ?string $itemLabel = null;

    protected bool $isAddable = true;

    protected bool $isDeletable = true;

    protected bool $isCloneable = false;

    protected bool $isReorderable = true;

    protected bool $isReorderableWithDragAndDrop = true;

    protected bool $isReorderableWithButtons = false;

    protected bool $hasItemNumbers = false;

    protected bool $hasItemHeaders = true;

    public function getType(): string
    {
        return 'repeater';
    }

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

    public function defaultItems(int $defaultItems): static
    {
        $this->defaultItems = max($defaultItems, 0);

        return $this;
    }

    public function getDefaultItems(): int
    {
        return $this->defaultItems;
    }

    public function minItems(int $minItems): static
    {
        $this->minItems = max($minItems, 0);

        return $this;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function maxItems(int $maxItems): static
    {
        $this->maxItems = max($maxItems, 0);

        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    /**
     * Start every row collapsed (only meaningful with `collapsible()`).
     */
    public function collapsed(bool $condition = true): static
    {
        $this->collapsed = $condition;

        return $this;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    public function grid(int $columns): static
    {
        $this->grid = max($columns, 1);

        return $this;
    }

    public function getGrid(): ?int
    {
        return $this->grid;
    }

    public function addActionLabel(?string $label): static
    {
        $this->addActionLabel = $label;

        return $this;
    }

    public function getAddActionLabel(): ?string
    {
        return $this->addActionLabel;
    }

    public function itemLabel(?string $label): static
    {
        $this->itemLabel = $label;

        return $this;
    }

    public function getItemLabel(): ?string
    {
        return $this->itemLabel;
    }

    /**
     * Allow adding rows (default true). Enforced alongside the `maxItems` cap.
     */
    public function addable(bool $condition = true): static
    {
        $this->isAddable = $condition;

        return $this;
    }

    public function isAddable(): bool
    {
        return $this->isAddable;
    }

    /**
     * Allow removing rows (default true). Enforced alongside the `minItems` floor.
     */
    public function deletable(bool $condition = true): static
    {
        $this->isDeletable = $condition;

        return $this;
    }

    public function isDeletable(): bool
    {
        return $this->isDeletable;
    }

    /**
     * Add a per-row clone action that duplicates the row (default off).
     */
    public function cloneable(bool $condition = true): static
    {
        $this->isCloneable = $condition;

        return $this;
    }

    public function isCloneable(): bool
    {
        return $this->isCloneable;
    }

    /**
     * Allow reordering rows. Defaults on (Filament's behavior); combine with
     * the two `reorderableWith*` modes to pick the mechanism.
     */
    public function reorderable(bool $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    public function isReorderable(): bool
    {
        return $this->isReorderable;
    }

    /**
     * Reorder rows by dragging the grip handle (default on when reorderable).
     */
    public function reorderableWithDragAndDrop(bool $condition = true): static
    {
        $this->isReorderableWithDragAndDrop = $condition;

        return $this;
    }

    public function isReorderableWithDragAndDrop(): bool
    {
        return $this->isReorderableWithDragAndDrop && $this->isReorderable;
    }

    /**
     * Reorder rows with up/down buttons in the header (default off).
     */
    public function reorderableWithButtons(bool $condition = true): static
    {
        $this->isReorderableWithButtons = $condition;

        return $this;
    }

    public function isReorderableWithButtons(): bool
    {
        return $this->isReorderableWithButtons && $this->isReorderable;
    }

    /**
     * Number the row headings ("1", "2", …) beside the item label.
     */
    public function itemNumbers(bool $condition = true): static
    {
        $this->hasItemNumbers = $condition;

        return $this;
    }

    public function hasItemNumbers(): bool
    {
        return $this->hasItemNumbers;
    }

    /**
     * Show the per-row header bar (default true). Disable to render rows with
     * no heading chrome.
     */
    public function itemHeaders(bool $condition = true): static
    {
        $this->hasItemHeaders = $condition;

        return $this;
    }

    public function hasItemHeaders(): bool
    {
        return $this->hasItemHeaders;
    }

    /**
     * The repeater's value is an array of rows — `array` plus the declared
     * min/max item counts (the row fields' own rules validate separately
     * under `items.*.field`, collected by Schema::getValidationRules()).
     *
     * @return array<int, string|object|Closure>
     */
    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();

        $rules[] = 'array';

        if ($this->minItems !== null) {
            $rules[] = "min:{$this->minItems}";
        }

        if ($this->maxItems !== null) {
            $rules[] = "max:{$this->maxItems}";
        }

        return $rules;
    }

    /**
     * The initial rows — `defaultItems` rows, each built from the row
     * fields' own defaults. The schema serializer's initialData() picks this
     * up, so the form opens with the declared number of rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefault(): mixed
    {
        $rows = [];

        for ($i = 0; $i < $this->defaultItems; $i++) {
            $row = [];

            foreach ($this->childComponents as $field) {
                $name = $field->getName();

                if ($name !== null) {
                    $row[$name] = $field->getDefault();
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->label,
            'helperText' => $this->helperText,
            'hint' => $this->hint,
            'hintIcon' => $this->hintIcon,
            'required' => $this->isRequired() ? true : null,
            'disabled' => $this->isDisabled() ? true : null,
            'readOnly' => $this->isReadOnly() ? true : null,
            'dehydrated' => $this->isDehydrated() ? null : false,
            'schema' => $this->serializeChildren($operation),
            'defaultItems' => $this->defaultItems > 0 ? $this->defaultItems : null,
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'collapsible' => $this->collapsible ? true : null,
            'collapsed' => $this->collapsed ? true : null,
            'grid' => $this->grid,
            'addActionLabel' => $this->addActionLabel,
            'itemLabel' => $this->itemLabel,
            'addable' => $this->isAddable() ? null : false,
            'deletable' => $this->isDeletable() ? null : false,
            'cloneable' => $this->isCloneable() ? true : null,
            'reorderable' => $this->isReorderable() ? null : false,
            'reorderableWithDragAndDrop' => $this->isReorderableWithDragAndDrop() ? null : false,
            'reorderableWithButtons' => $this->isReorderableWithButtons() ? true : null,
            'itemNumbers' => $this->hasItemNumbers() ? true : null,
            'itemHeaders' => $this->hasItemHeaders() ? null : false,
        ]);
    }

    /**
     * Serialize the row schema to contract nodes.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeChildren(?string $operation = null): array
    {
        return array_map(
            static fn (Component $component): array => $component->toArray($operation),
            $this->childComponents,
        );
    }
}
