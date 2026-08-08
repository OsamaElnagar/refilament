<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Illuminate\Support\Traits\Macroable;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;

/**
 * Free-text column filter.
 *
 * Mirrors Filament's TextFilter: a filter keyed by `name` that constrains a
 * column to rows containing the submitted text (`LIKE %term%`). Clients send
 * `filter[<name>]=<term>` to the index endpoint; the React runtime debounces
 * the input (docs/CONTRACT.md, "Tables").
 */
class TextFilter
{
    use CanBeConfigured;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected ?string $attribute = null;

    protected ?string $placeholder = null;

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

    /**
     * The query column this filter constrains. Defaults to the filter name.
     */
    public function attribute(?string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

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

    public function getAttribute(): string
    {
        return $this->attribute ?? (string) $this->name;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
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
            'type' => 'text',
        ];

        if ($this->placeholder !== null) {
            $payload['placeholder'] = $this->placeholder;
        }

        return $payload;
    }
}
