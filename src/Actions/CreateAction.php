<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

/**
 * The create action for resource list pages (slice 1.10 — docs/ROADMAP.md
 * "1.10 Page header actions").
 *
 * Mirrors Filament's `Actions\CreateAction`: the default header action on a
 * list page, labelled "New {Model}" with a plus icon. Clicking it navigates
 * to the resource's create page when one is registered (Filament's
 * `getDefaultActionUrl`), and falls back to a modal hosting the resource's
 * form when there is no create page — the exact behavior the consumer chose
 * for the default.
 *
 * This class carries only the defaults; the page resolver
 * (Resources\Pages\Page::serializeHeaderActions) fills in the model label
 * and the url/modal fallback at request time, where the resource context
 * exists — the same request/response model every other config surface uses
 * (no server memory, no closures across the wire).
 */
class CreateAction extends Action
{
    /**
     * The singular model label the default "New {Model}" label is built from
     * — resolved from the resource by the page serializer. An explicit
     * `label()` always wins over it.
     */
    protected ?string $modelLabel = null;

    /**
     * Whether the consumer set an explicit label — the serializer only fills
     * the model label when this is false.
     */
    protected bool $hasCustomLabel = false;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'create')->icon('plus');
    }

    /**
     * The singular model label ("Post") the default "New Post" label is
     * derived from. The page serializer resolves it from the resource; a
     * consumer may also set it explicitly.
     */
    public function modelLabel(?string $modelLabel): static
    {
        $this->modelLabel = $modelLabel;

        return $this;
    }

    public function label(?string $label): static
    {
        $this->hasCustomLabel = true;

        return parent::label($label);
    }

    public function hasCustomLabel(): bool
    {
        return $this->hasCustomLabel;
    }

    public function getLabel(): string
    {
        if ($this->hasCustomLabel) {
            return parent::getLabel();
        }

        return $this->modelLabel !== null ? 'New '.$this->modelLabel : 'New';
    }
}
