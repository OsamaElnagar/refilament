<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Illuminate\Support\Traits\Macroable;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;

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
    use CanBeConfigured;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected ?string $placeholder = null;

    final public function __construct(protected ?string $name = null)
    {
        $this->configure();
    }

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
                ['value' => '', 'label' => $this->placeholder ?? __('refilament::tables.filters.trashed.without_trashed')],
                ['value' => 'with', 'label' => __('refilament::tables.filters.trashed.with_trashed')],
                ['value' => 'only', 'label' => __('refilament::tables.filters.trashed.only_trashed')],
            ],
        ];

        return $payload;
    }
}
