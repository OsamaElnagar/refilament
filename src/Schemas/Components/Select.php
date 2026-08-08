<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Closure;
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

    public function toArray(): array
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

        $payload = $this->filterNullValues([
            ...parent::toArray(),
            'multiple' => $this->isMultiple() ? true : null,
            'searchable' => $this->isSearchable() ? true : null,
        ]);

        if ($this->hasOptionsResolver()) {
            unset($payload['options']);
        }

        return $payload;
    }
}
