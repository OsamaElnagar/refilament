<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

/**
 * Soft-delete filter (slice 2.2).
 *
 * Mirrors Filament's TrashedFilter: a ternary control that shows either all
 * records, only trashed ones, or excludes trashed ones entirely. The client
 * sends `filter[<name>]=with` / `=only` / (absent for "without trashed"); the
 * table's query applies the matching soft-delete scope server-side.
 *
 * Requires the model to use Illuminate\Database\Eloquent\SoftDeletes.
 */
class TrashedFilter
{
    protected ?string $label = null;

    protected ?string $placeholder = null;

    final public function __construct(protected ?string $name = null) {}

    public static function make(?string $name = null): static
    {
        return new static($name ?? static::getDefaultName());
    }

    public static function getDefaultName(): ?string
    {
        return 'trashed';
    }

    public function label(?string $label): static
    {
        $this->label = $label;

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
        return $this->label ?? (string) $this->name;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Serialize the filter definition (docs/CONTRACT.md, "Bulk actions").
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'type' => 'trashed',
            'options' => [
                ['value' => '', 'label' => $this->placeholder ?? 'Without trashed'],
                ['value' => 'with', 'label' => 'With trashed'],
                ['value' => 'only', 'label' => 'Only trashed'],
            ],
        ];

        return $payload;
    }
}
