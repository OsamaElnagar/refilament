<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

/**
 * Discrete column filter (slice 8).
 *
 * Mirrors Filament's SelectFilter: a filter keyed by `name` that constrains a
 * column to one of the configured static `options`. Clients send
 * `filter[<name>]=<value>` (or repeated for `multiple`) to the index endpoint,
 * which narrows the query server-side (docs/CONTRACT.md, "Tables").
 *
 * Deferred: relationship filters, searchable/static option closures, default
 * state, filter indicators.
 */
class SelectFilter
{
    protected ?string $label = null;

    protected ?string $attribute = null;

    /** @var array<string, string> value => label */
    protected array $options = [];

    protected bool $multiple = false;

    final public function __construct(protected ?string $name = null) {}

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
     * The query column this filter constrains. Defaults to the filter name.
     */
    public function attribute(?string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * @param  array<string, string>  $options  value => label
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Allow several values at once (sent as repeated `filter[<name>][]`
     * params, matched with WHERE IN).
     */
    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? (string) $this->name;
    }

    public function getAttribute(): string
    {
        return $this->attribute ?? (string) $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
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
            'type' => 'select',
            'options' => array_map(
                static fn (string $value, string $optionLabel): array => ['value' => $value, 'label' => $optionLabel],
                array_keys($this->options),
                array_values($this->options),
            ),
        ];

        if ($this->isMultiple()) {
            $payload['multiple'] = true;
        }

        return $payload;
    }
}
