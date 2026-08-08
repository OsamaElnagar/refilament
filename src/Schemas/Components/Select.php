<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Select field (slices 3 + 4).
 *
 * Static options (`options()`), client-side search and multiple selection are
 * serialized as data. Dependent options (slice 4) use `dependsOn()` plus a
 * server-side `resolveOptionsUsing()` closure, resolved through the typed
 * resolve-options endpoint — never serialized. Deferred: relationship options,
 * create-option, server-side search, reorderable, optionsLimit.
 */
class Select extends Component
{
    protected bool $isMultiple = false;

    protected bool $isSearchable = false;

    /**
     * Server-side resolver for dependent options (docs/CONTRACT.md, "Options").
     *
     * @var Closure(array<string, mixed>): array<string|int, string>|null
     */
    protected ?Closure $optionsResolver = null;

    /**
     * The model a `relationship()` select resolves its options from — the
     * owner model the relationship lives on, or the related model class
     * itself (slice C1).
     *
     * @var string|Model|null
     */
    protected mixed $relationshipModel = null;

    /**
     * The relationship method name on the owner model whose related records
     * become this select's options (slice C1 — mirrors Filament's
     * `->relationship('account', 'name')`).
     */
    protected ?string $relationshipName = null;

    protected ?string $relationshipLabelAttribute = null;

    /**
     * Per-record option label resolver (mirrors Filament's
     * `getOptionLabelFromRecordUsing()`). Receives the related record and
     * returns its display text.
     *
     * @var Closure(object): string|null
     */
    protected ?Closure $optionLabelResolver = null;

    public function getType(): string
    {
        return 'select';
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    /**
     * Register the server-side resolver for this field's options.
     *
     * The resolver receives the full flat form data and returns a
     * value => label map, e.g. `fn (array $data): array => $data['country']
     * === 'us' ? ['al' => 'Alabama'] : []`. It never survives serialization:
     * fields with a resolver omit `options` from the payload and the React
     * runtime fetches fresh options via the resolve-options endpoint.
     *
     * @param  Closure(array<string, mixed>): array<string|int, string>  $resolver
     */
    public function resolveOptionsUsing(Closure $resolver): static
    {
        $this->optionsResolver = $resolver;

        return $this;
    }

    public function hasOptionsResolver(): bool
    {
        return $this->optionsResolver !== null;
    }

    /**
     * Bind the model the select's relationship lives on — the owner model
     * (`->relationship('user', 'name')->model(Post::class)`), or directly
     * the related model class. Required for `relationship()` selects; our
     * schemas have no Livewire form context to infer the model from.
     */
    public function model(string|Model|null $model): static
    {
        $this->relationshipModel = $model;

        return $this;
    }

    /**
     * Populate this select from a relationship's related records, labeled by
     * an attribute (mirrors Filament's `Select::relationship('account',
     * 'name')`). Options are resolved server-side at serialization — the
     * payload ships the resolved list and client-side search filters it, with
     * no round trip. (A truly huge related table would move to the
     * resolve-options endpoint pattern instead; deferred.)
     */
    public function relationship(string $relationship, string $labelAttribute): static
    {
        $this->relationshipName = $relationship;
        $this->relationshipLabelAttribute = $labelAttribute;

        return $this;
    }

    /**
     * A per-record label resolver for `relationship()` options — the Ahram
     * idiom `getOptionLabelFromRecordUsing(fn (Account $account) => "{$account->code} — {$account->name}")`.
     *
     * @param  Closure(object $record): string  $resolver
     */
    public function getOptionLabelFromRecordUsing(Closure $resolver): static
    {
        $this->optionLabelResolver = $resolver;

        return $this;
    }

    public function isRelationship(): bool
    {
        return $this->relationshipName !== null;
    }

    /**
     * Resolve a relationship select's options: the related records of the
     * bound model's relationship, keyed by primary key (string-cast) and
     * labeled by the label attribute (or the per-record resolver when set).
     *
     * @return array<string, string>
     */
    protected function resolveRelationshipOptions(): array
    {
        $model = $this->relationshipModel;

        if (is_string($model)) {
            $model = new $model;
        }

        if (! $model instanceof Model || $this->relationshipName === null) {
            throw new LogicException(
                "Select field [{$this->getName()}] must declare [relationship()] and [model()] to load relationship options.",
            );
        }

        $relatedClass = $model->{$this->relationshipName}()->getRelated();

        $options = [];

        foreach ($relatedClass::query()->get() as $record) {
            $label = $this->optionLabelResolver !== null
                ? (string) ($this->optionLabelResolver)($record)
                : (string) $record->getAttribute((string) $this->relationshipLabelAttribute);

            $options[(string) $record->getKey()] = $label;
        }

        return $options;
    }

    /**
     * Evaluate the options resolver against the current form data and return
     * the options in the contract shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{value: string, label: string}>
     */
    public function resolveOptions(array $data): array
    {
        if ($this->optionsResolver === null) {
            return [];
        }

        $options = ($this->optionsResolver)($data);

        return $this->serializeOptionMap($options);
    }

    public function toArray(?string $operation = null): array
    {
        // Dependent fields resolve options on demand; a serialized static
        // list would go stale the moment a dependency changes. A resolver
        // without `dependsOn` would serialize no options and never be fetched
        // by the React runtime — fail fast instead of shipping a broken field.
        if ($this->hasOptionsResolver() && $this->getDependsOn() === null) {
            throw new LogicException(
                "Select field [{$this->getName()}] must declare [dependsOn()] to use [resolveOptionsUsing()].",
            );
        }

        // Relationship selects (slice C1) resolve their options server-side
        // at serialization — the payload ships the resolved list, and the
        // client-side search filters it. A relationship conflicts with a
        // static options list or a dependent resolver.
        if ($this->isRelationship()) {
            if ($this->optionsResolver !== null || $this->options !== null) {
                throw new LogicException(
                    "Select field [{$this->getName()}] cannot combine [relationship()] with [options()] or [resolveOptionsUsing()].",
                );
            }

            $this->options = $this->resolveRelationshipOptions();
        }

        $payload = $this->filterNullValues([
            ...parent::toArray($operation),
            'multiple' => $this->isMultiple() ? true : null,
            'searchable' => $this->isSearchable() ? true : null,
        ]);

        if ($this->hasOptionsResolver()) {
            unset($payload['options']);
        }

        return $payload;
    }
}
