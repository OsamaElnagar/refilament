<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Checkbox list field.
 *
 * A multi-select rendered as a checkbox group. Mirrors Filament's CheckboxList
 * config surface where it is pure data: options share the base component's
 * value => label map (the same contract shape Select and Radio use), the
 * selected state is an array of values, `searchable()` filters options
 * client-side, `descriptions()` adds per-option helper text, `columns()`
 * arranges the options in a grid (clamped to 1-6, the renderer's domain) and
 * `bulkToggleable()` renders select-all / deselect-all actions.
 *
 * This mirrors the config API only. There is no server-side component
 * persistence: everything a CheckboxList needs is serialized as data (search,
 * bulk toggle and selection are all client state).
 *
 * Deferred for v1: relationship binding, per-option disable callbacks,
 * allowHtml, grid direction.
 */
class CheckboxList extends Component
{
    protected bool $isSearchable = false;

    protected bool $isBulkToggleable = false;

    protected ?int $columns = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $descriptions = null;

    public function getType(): string
    {
        return 'checkbox_list';
    }

    public function searchable(bool $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    public function bulkToggleable(bool $condition = true): static
    {
        $this->isBulkToggleable = $condition;

        return $this;
    }

    public function isBulkToggleable(): bool
    {
        return $this->isBulkToggleable;
    }

    /**
     * Arrange the options in a grid of equal-width columns (1-6, Tailwind's
     * supported domain). Clamped so the serialized value always matches what
     * the renderer can express — same convention as Radio.
     */
    public function columns(int $columns): static
    {
        $this->columns = min(max($columns, 1), 6);

        return $this;
    }

    public function getColumns(): ?int
    {
        return $this->columns;
    }

    /**
     * Set per-option descriptions shown under each label.
     *
     * @param  array<string, string>  $descriptions
     */
    public function descriptions(array $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getDescriptions(): ?array
    {
        return $this->descriptions;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'searchable' => $this->isSearchable() ? true : null,
            'bulkToggleable' => $this->isBulkToggleable() ? true : null,
            'columns' => $this->columns,
            'descriptions' => $this->descriptions,
        ]);
    }
}
