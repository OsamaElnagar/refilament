<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Key value field — an editable table of key/value rows.
 *
 * Mirrors Filament's config API where it is pure data. Rows may be added,
 * removed and reordered; keys and values are independently editable. The
 * React runtime owns the rows as an array of `{ key, value }` objects.
 *
 * Deferred for v1: per-action modification callbacks, reorder animation
 * duration, deprecated `disable*()` aliases.
 */
class KeyValue extends Component
{
    protected bool $isAddable = true;

    protected bool $isDeletable = true;

    protected bool $canEditKeys = true;

    protected bool $canEditValues = true;

    protected bool $isReorderable = false;

    protected ?string $addActionLabel = null;

    protected ?string $keyLabel = null;

    protected ?string $valueLabel = null;

    protected ?string $keyPlaceholder = null;

    protected ?string $valuePlaceholder = null;

    public function getType(): string
    {
        return 'key_value';
    }

    public function addable(bool $condition = true): static
    {
        $this->isAddable = $condition;

        return $this;
    }

    public function deletable(bool $condition = true): static
    {
        $this->isDeletable = $condition;

        return $this;
    }

    public function editableKeys(bool $condition = true): static
    {
        $this->canEditKeys = $condition;

        return $this;
    }

    public function editableValues(bool $condition = true): static
    {
        $this->canEditValues = $condition;

        return $this;
    }

    public function reorderable(bool $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    public function addActionLabel(string $label): static
    {
        $this->addActionLabel = $label;

        return $this;
    }

    public function keyLabel(string $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function valueLabel(string $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function keyPlaceholder(?string $placeholder): static
    {
        $this->keyPlaceholder = $placeholder;

        return $this;
    }

    public function valuePlaceholder(?string $placeholder): static
    {
        $this->valuePlaceholder = $placeholder;

        return $this;
    }

    public function isAddable(): bool
    {
        return $this->isAddable;
    }

    public function isDeletable(): bool
    {
        return $this->isDeletable;
    }

    public function canEditKeys(): bool
    {
        return $this->canEditKeys;
    }

    public function canEditValues(): bool
    {
        return $this->canEditValues;
    }

    public function isReorderable(): bool
    {
        return $this->isReorderable;
    }

    public function getAddActionLabel(): string
    {
        return $this->addActionLabel ?? 'Add';
    }

    public function getKeyLabel(): string
    {
        return $this->keyLabel ?? 'Key';
    }

    public function getValueLabel(): string
    {
        return $this->valueLabel ?? 'Value';
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'addable' => $this->isAddable() ? null : false,
            'deletable' => $this->isDeletable() ? null : false,
            'editableKeys' => $this->canEditKeys() ? null : false,
            'editableValues' => $this->canEditValues() ? null : false,
            'reorderable' => $this->isReorderable() ? true : null,
            'addActionLabel' => $this->addActionLabel,
            'keyLabel' => $this->keyLabel,
            'valueLabel' => $this->valueLabel,
            'keyPlaceholder' => $this->keyPlaceholder,
            'valuePlaceholder' => $this->valuePlaceholder,
        ]);
    }
}
