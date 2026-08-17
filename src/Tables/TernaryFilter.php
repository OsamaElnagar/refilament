<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Illuminate\Support\Traits\Macroable;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;

/**
 * Ternary filter — allows filtering with three states: 'with' (show all),
 * 'only' (show only matching), or '' (show only non-matching / base query).
 *
 * Mirrors Filament's TernaryFilter: commonly used for soft-delete aware
 * filtering where '' (default) excludes trashed, 'with' includes everything,
 * and 'only' shows only the trashed records.
 */
class TernaryFilter
{
    use CanBeConfigured;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    final public function __construct(protected ?string $name = null)
    {
        $this->configure();
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Treat the filter label as a translation key resolved through the app's
     * translator when the filter is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        $label = $this->label ?? (string) $this->name;

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    /**
     * Serialize the filter definition (docs/CONTRACT.md, "Tables").
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'type' => 'ternary',
        ];

        return $payload;
    }
}
