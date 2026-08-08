<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Fieldset layout (slice 2.6).
 *
 * Groups its children inside a bordered box drawn from the native `<fieldset>`
 * element with a `<legend>` label (mirrors Filament's `Fieldset`, which defaults
 * to a 2-column grid). Deferred: `canBeMarkedAsRequired` legend asterisk,
 * extra attribute bags, contained toggle.
 */
class Fieldset extends Layout
{
    protected ?string $label = null;

    protected int $columns = 2;

    public static function make(?string $label = null): static
    {
        // The base Component::make() forwards its argument to a (final)
        // constructor that sets the name — Fieldset's argument is a label,
        // so the fluent factory sets it directly.
        $fieldset = new static;

        if ($label !== null) {
            $fieldset->label($label);
        }

        return $fieldset;
    }

    public function getType(): string
    {
        return 'fieldset';
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? '';
    }

    public function columns(int $columns): static
    {
        $this->columns = max($columns, 1);

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function toArray(): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'label' => $this->label,
            'columns' => $this->columns,
            'schema' => $this->serializeChildren(),
        ]);
    }
}
