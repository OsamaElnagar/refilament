<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Toggle buttons field — a segmented button group select.
 *
 * Mirrors Filament's ToggleButtons config API where it is pure data. Renders
 * one button per option (instead of a dropdown); state is a single value by
 * default, or an array when `multiple()`. Per-option icons/colors/tooltips are
 * serialized as value-keyed maps for the React runtime. The `boolean()`
 * preset yields a true/false (1/0) pair with success/danger colors.
 *
 * As with the other schemas fields, configuration is imperative (no closures).
 *
 * Deferred for v1: grid columns + direction, option-level disable, enum state
 * casts.
 */
class ToggleButtons extends Component
{
    protected bool $isMultiple = false;

    protected bool $isInline = false;

    protected bool $isGrouped = false;

    protected bool $areButtonLabelsHidden = false;

    /**
     * @var array<string, string>
     */
    protected array $icons = [];

    /**
     * @var array<string|int, string>
     */
    protected array $colors = [];

    /**
     * @var array<string, string>
     */
    protected array $tooltips = [];

    public function getType(): string
    {
        return 'toggle_buttons';
    }

    /**
     * Allow selecting several options at once (state becomes an array).
     */
    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    /**
     * Render the buttons in a single inline row instead of a grid.
     */
    public function inline(bool $condition = true): static
    {
        $this->isInline = $condition;

        return $this;
    }

    /**
     * Render the buttons as a joined button group.
     */
    public function grouped(bool $condition = true): static
    {
        $this->isGrouped = $condition;

        return $this;
    }

    /**
     * Hide the option labels, rendering icon-only buttons.
     */
    public function hiddenButtonLabels(bool $condition = true): static
    {
        $this->areButtonLabelsHidden = $condition;

        return $this;
    }

    /**
     * Per-option icon names, keyed by option value.
     *
     * @param  array<string, string>  $icons
     */
    public function icons(array $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /**
     * Per-option color names, keyed by option value.
     *
     * @param  array<string|int, string>  $colors
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * Per-option tooltips, keyed by option value.
     *
     * @param  array<string, string>  $tooltips
     */
    public function tooltips(array $tooltips): static
    {
        $this->tooltips = $tooltips;

        return $this;
    }

    /**
     * A true/false toggle: options `1`/`0` labeled by the given strings, with
     * success/danger colors.
     */
    public function boolean(?string $trueLabel = null, ?string $falseLabel = null): static
    {
        $this->options([
            '1' => $trueLabel ?? 'Yes',
            '0' => $falseLabel ?? 'No',
        ]);

        $this->colors([
            '1' => 'success',
            '0' => 'danger',
        ]);

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function isInline(): bool
    {
        return $this->isInline;
    }

    public function isGrouped(): bool
    {
        return $this->isGrouped;
    }

    public function areButtonLabelsHidden(): bool
    {
        return $this->areButtonLabelsHidden;
    }

    /**
     * @return array<string, string>
     */
    public function getIcons(): array
    {
        return $this->icons;
    }

    /**
     * @return array<string|int, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * @return array<string, string>
     */
    public function getTooltips(): array
    {
        return $this->tooltips;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'multiple' => $this->isMultiple() ? true : null,
            'inline' => $this->isInline() ? true : null,
            'grouped' => $this->isGrouped() ? true : null,
            'hiddenButtonLabels' => $this->areButtonLabelsHidden() ? true : null,
            'icons' => $this->icons !== [] ? $this->icons : null,
            'colors' => $this->colors !== [] ? $this->colors : null,
            'tooltips' => $this->tooltips !== [] ? $this->tooltips : null,
        ]);
    }
}
