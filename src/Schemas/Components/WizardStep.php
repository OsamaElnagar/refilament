<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * One step inside a Wizard layout (mirrors
 * `Filament\Schemas\Components\Wizard\Step`).
 *
 * Carries a label, an optional description and icon, and its own schema of
 * fields/layouts. The wizard serializes each step as a `wizard-step` node;
 * the client renders only the active step's fields.
 */
class WizardStep extends Layout
{
    protected ?string $label = null;

    protected ?string $description = null;

    protected ?string $icon = null;

    public static function make(?string $label = null): static
    {
        $step = new static;

        if ($label !== null) {
            $step->label($label);
        }

        return $step;
    }

    public function getType(): string
    {
        return 'wizard-step';
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        $label = $this->label ?? '';

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'schema' => $this->serializeChildren($operation),
        ]);
    }
}
