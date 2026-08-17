<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

/**
 * Color entry — a value rendered as a colored swatch (slice 3.3), extended with
 * read-only copy (slice 3.9 / docs/PLAN_COLUMNS_INFOLISTS.md §2).
 *
 * Mirrors `Filament\Infolists\Components\ColorEntry`.
 * The color can be set statically or resolved from the bound record via
 * `->getStateUsing()`. A `->copyable()` swatch copies the value on click;
 * `->copyableState()` overrides what is copied.
 *
 * Display kinds (badge / color / icon / url) compose with the existing
 * `->badge()`, `->color()`, `->icon()`, `->url()` methods inherited from
 * `Entry`, so the same server-side idioms that work for table columns also
 * work for infolist entries.
 */
class ColorEntry extends Entry
{
    protected bool $isCopyable = false;

    protected string|int|float|null $copyableState = null;

    protected string $copyMessage = 'Copied!';

    public function getType(): string
    {
        return 'color_entry';
    }

    /**
     * Make each swatch copy its value on click (mirrors Filament's
     * `copyable()`).
     */
    public function copyable(bool $condition = true): static
    {
        $this->isCopyable = $condition;

        return $this;
    }

    /**
     * Override the value copied from a copyable swatch. Pure data (a plain
     * scalar) — the deferred closure form never survives serialization.
     */
    public function copyableState(string|int|float|null $state): static
    {
        $this->copyableState = $state;

        return $this;
    }

    /**
     * The toast text shown after a copy (mirrors Filament's `copyMessage()`).
     */
    public function copyMessage(string $message): static
    {
        $this->copyMessage = $message;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->isCopyable;
    }

    public function getCopyableState(): string|int|float|null
    {
        return $this->copyableState;
    }

    public function getCopyMessage(): string
    {
        return $this->copyMessage;
    }

    public function toArray(?string $operation = null): array
    {
        $payload = parent::toArray($operation);

        if ($this->isCopyable) {
            $payload['copyable'] = true;

            if ($this->copyableState !== null) {
                $payload['copyableState'] = $this->copyableState;
            }

            if ($this->copyMessage !== 'Copied!') {
                $payload['copyMessage'] = $this->copyMessage;
            }
        }

        return $payload;
    }
}
