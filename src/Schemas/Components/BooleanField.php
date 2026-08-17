<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Shared base for the boolean fields — checkbox and toggle (slice 1.4).
 *
 * Mirrors Filament's Checkbox/Toggle config surface: the state defaults to
 * false, the `boolean` validation rule always applies, `accepted()` adds the
 * Laravel `accepted` rule (the faithful way to require consent), and
 * `inline()` renders the label beside the control instead of above it.
 */
abstract class BooleanField extends Component
{
    protected int|string|bool|float|null $default = false;

    protected bool $isInline = false;

    public function configure(): static
    {
        parent::configure();

        // The boolean rule is intrinsic to a checkbox/toggle — added after
        // the global configureUsing() defaults, before any fluent calls.
        $this->pushValidationRules(['boolean']);

        return $this;
    }

    /**
     * Require the field to be checked (Laravel's `accepted` rule) — the
     * faithful way to gate a boolean on consent; a bare `required()` only
     * checks the key is present, which an unchecked box satisfies.
     */
    public function accepted(bool $condition = true): static
    {
        if ($condition) {
            $this->pushValidationRules(['accepted']);
        }

        return $this;
    }

    /**
     * Render the label beside the control instead of above it — Filament's
     * signature inline boolean layout.
     */
    public function inline(bool $condition = true): static
    {
        $this->isInline = $condition;

        return $this;
    }

    public function isInline(): bool
    {
        return $this->isInline;
    }

    /**
     * @deprecated Use `rules()` instead.
     *
     * @param  array<int, string>  $rules
     */
    public function validation(array $rules): static
    {
        parent::validation($rules);

        // The boolean rule is intrinsic to a checkbox/toggle — never let a
        // custom rule list drop it.
        $this->pushValidationRules(['boolean']);

        return $this;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'inline' => $this->isInline() ? true : null,
        ]);
    }
}
