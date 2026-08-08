<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * A single tab inside a Tabs layout (slice 2.6, mirrors
 * `Filament\Schemas\Components\Tabs\Tab`).
 *
 * Each tab carries a label plus its own schema of fields/layouts. Deferred:
 * badge, icon, deferred badges, per-tab query modification.
 */
class Tab extends Layout
{
    protected ?string $label = null;

    public static function make(?string $label = null): static
    {
        // The base Component::make() forwards its argument to a (final)
        // constructor that sets the name — Tab's argument is a label, so the
        // fluent factory sets it directly.
        $tab = new static;

        if ($label !== null) {
            $tab->label($label);
        }

        return $tab;
    }

    public function getType(): string
    {
        return 'tab';
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

    public function toArray(): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'label' => $this->label,
            'schema' => $this->serializeChildren(),
        ]);
    }
}
