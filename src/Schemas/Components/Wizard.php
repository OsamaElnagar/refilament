<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use LogicException;

/**
 * Wizard layout (mirrors `Filament\Schemas\Components\Wizard`) — a
 * multi-step form container. Each child is a `Wizard\Step` carrying its own
 * label, optional description/icon and schema; the client renders a step
 * indicator and shows one step at a time with Back/Next navigation.
 *
 * The active step is pure client state (like Tabs) — navigating never hits
 * the server, and the whole form's values submit together through the typed
 * submit endpoint when the form is saved. `startOnStep(int)` seeds the first
 * visible step (1-indexed, mirroring Filament); `skippable(bool)` lets the
 * client advance without visiting every step. Deferred: custom
 * next/previous action labels, `persistStepInQueryString`.
 */
class Wizard extends Layout
{
    protected int $startOnStep = 1;

    protected bool $skippable = false;

    public function getType(): string
    {
        return 'wizard';
    }

    /**
     * @param  array<int, Component>|Component  $steps
     */
    public function steps(array|Component $steps): static
    {
        foreach (is_array($steps) ? $steps : [$steps] as $step) {
            if (! $step instanceof WizardStep) {
                throw new LogicException('Wizard children must be instances of ['.WizardStep::class.'].');
            }

            $this->childComponents[] = $step;
        }

        return $this;
    }

    public function startOnStep(int $startOnStep): static
    {
        $this->startOnStep = max($startOnStep, 1);

        return $this;
    }

    public function getStartOnStep(): int
    {
        return $this->startOnStep;
    }

    public function skippable(bool $condition = true): static
    {
        $this->skippable = $condition;

        return $this;
    }

    public function isSkippable(): bool
    {
        return $this->skippable;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'startOnStep' => $this->startOnStep > 1 ? $this->startOnStep : null,
            'skippable' => $this->skippable ? true : null,
            'schema' => $this->serializeChildren($operation),
        ]);
    }
}
