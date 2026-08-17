<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

use Illuminate\Support\Arr;
use Refilament\Refilament\Schemas\Components\Component;

/**
 * Repeatable entry (slice: RepeatableEntry — PLAN §3, Suggested order 3).
 *
 * A read-only list entry: the state (an array of items — a JSON column, a
 * relation, or a `getStateUsing()` result) renders each item through a
 * declared child entry schema. Mirrors `Filament\Infolists\Components\
 * RepeatableEntry`, minus editing: the shipped node carries `items`, a list
 * of per-item child-entry nodes, each already resolved server-side against
 * that item's data — closures never survive the wire.
 *
 * `RepeatableEntry::make('team_members')->schema([TextEntry::make('name'), ...])`.
 * Each child entry's value resolves against its item (an array) via data_get,
 * so dot-notation names also work within an item.
 */
class RepeatableEntry extends Entry
{
    /** @var array<int, Component> */
    protected array $childComponents = [];

    public function getType(): string
    {
        return 'repeatable';
    }

    /**
     * The child entry schema describing how each item renders.
     *
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

    public function toArray(?string $operation = null): array
    {
        $record = $this->getRecord();
        $state = $record !== null ? $this->getStateFor($record) : null;

        $items = [];

        foreach (Arr::wrap($state) as $item) {
            // Clone each child entry so it can be bound to this item without
            // mutating the shared schema (one entry instance per item).
            $items[] = array_map(
                static function (Component $component) use ($item, $operation): array {
                    return (clone $component)->record($item)->toArray($operation);
                },
                $this->childComponents,
            );
        }

        $payload = [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'items' => $items,
        ];

        if ($this->placeholder !== null) {
            $payload['placeholder'] = $this->placeholder;
        }

        if ($this->columnSpan !== null) {
            $payload['columnSpan'] = $this->columnSpan;
        }

        return $payload;
    }
}
