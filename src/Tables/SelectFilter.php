<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;

/**
 * Discrete column filter (slice 8), extended with relationship filters.
 *
 * Mirrors Filament's SelectFilter: a filter keyed by `name` that constrains a
 * column to one of the configured static `options` — or, via `relationship()`,
 * to the related records of a to-one relationship (options resolved from the
 * related table, matching done with `whereHas`). Clients send
 * `filter[<name>]=<value>` (or repeated for `multiple`) to the index endpoint,
 * which narrows the query server-side (docs/CONTRACT.md, "Tables").
 *
 * Deferred: searchable/static option closures, default state, filter
 * indicators.
 */
class SelectFilter
{
    use CanBeConfigured;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected ?string $attribute = null;

    /** @var array<string, string> value => label */
    protected array $options = [];

    protected ?string $placeholder = null;

    protected bool $multiple = false;

    protected ?string $relationship = null;

    protected ?string $relationshipTitleAttribute = null;

    protected ?Closure $modifyRelationshipQueryUsing = null;

    /**
     * The table's model, injected by the table before serialization so a
     * relationship filter can resolve its relation.
     */
    protected ?Model $model = null;

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

    /**
     * @param  array<string, string>  $options  value => label
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * The select's placeholder, rendered by the client when the filter is
     * empty (mirrors Filament's SelectFilter::placeholder()).
     */
    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

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

    /**
     * Constrain the filter to a to-one relationship on the table's model
     * (mirrors Filament's SelectFilter::relationship()). The filter's options
     * are resolved from the related records — keyed by their primary key,
     * labelled by `$titleAttribute`, ordered by that attribute — and applying
     * the filter narrows the query with `whereHas` against those keys.
     */
    public function relationship(string $name, string $titleAttribute, ?Closure $modifyQueryUsing = null): static
    {
        $this->relationship = $name;
        $this->relationshipTitleAttribute = $titleAttribute;
        $this->modifyRelationshipQueryUsing = $modifyQueryUsing;

        return $this;
    }

    /**
     * The table's model, needed to resolve the relationship. The table injects
     * it before serializing or applying the filter.
     */
    public function setModel(?Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function queriesRelationships(): bool
    {
        return $this->relationship !== null;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationship;
    }

    public function getRelationshipTitleAttribute(): ?string
    {
        return $this->relationshipTitleAttribute;
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
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->queriesRelationships() ? $this->getRelationshipOptions() : $this->options;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * The related records' query for a relationship filter — constrained
     * (optionally) by `modifyQueryUsing`, ordered by the title attribute so
     * the dropdown lists options alphabetically (mirrors Filament).
     *
     * @return Builder<Model>
     */
    protected function getRelationshipQuery(): Builder
    {
        $relationship = Relation::noConstraints(function (): Relation {
            $model = $this->model ?? throw new LogicException('The filter model must be set before resolving a relationship filter.');

            $relationship = $model->{$this->relationship}();

            if (! $relationship instanceof Relation) {
                throw new LogicException(
                    sprintf('The relationship [%s] does not exist on the model [%s].', $this->relationship, $model::class),
                );
            }

            return $relationship;
        });

        $query = $relationship->getRelated()->newQuery();

        if ($this->modifyRelationshipQueryUsing !== null) {
            $query = ($this->modifyRelationshipQueryUsing)($query) ?? $query;
        }

        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->qualifyColumn($this->relationshipTitleAttribute));
        }

        return $query;
    }

    /**
     * The filter's options from the related records: primary key => title
     * attribute, for a relationship filter.
     *
     * @return array<string, string>
     */
    protected function getRelationshipOptions(): array
    {
        $query = $this->getRelationshipQuery();

        return $query->pluck($this->relationshipTitleAttribute, $query->getModel()->getKeyName())->all();
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
                array_keys($this->getOptions()),
                array_values($this->getOptions()),
            ),
        ];

        if ($this->isMultiple()) {
            $payload['multiple'] = true;
        }

        if ($this->placeholder !== null) {
            $payload['placeholder'] = $this->placeholder;
        }

        return $payload;
    }
}
