<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Tags input field.
 *
 * Mirrors Filament's TagsInput config API where it is pure data. State is an
 * array of strings: the React runtime holds tags as an array and submits an
 * array. When the initial value arrives as a single string (and a `separator`
 * is configured), the client splits it into tags; the field always dehydrates
 * as an array.
 *
 * As with the other schemas fields, configuration is imperative (no closures):
 * `separator()`, `splitKeys()`, `suggestions()` and the tag prefix/suffix are
 * plain values serialized to the contract node.
 *
 * Deferred for v1: color-coded badges, character stripping, state casts,
 * affixes.
 */
class TagsInput extends Component
{
    protected bool $isReorderable = false;

    protected ?string $separator = ',';

    /**
     * @var array<string>
     */
    protected array $splitKeys = [];

    /**
     * @var array<string>
     */
    protected array $suggestions = [];

    protected ?string $tagPrefix = null;

    protected ?string $tagSuffix = null;

    public function getType(): string
    {
        return 'tags_input';
    }

    /**
     * Allow the tags to be reordered by dragging.
     */
    public function reorderable(bool $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    /**
     * The separator used to split a string initial value into tags. Pass
     * `null` to keep state strictly an array. Defaults to `,`.
     */
    public function separator(?string $separator = ','): static
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * @param  array<string>  $keys
     */
    public function splitKeys(array $keys): static
    {
        $this->splitKeys = $keys;

        return $this;
    }

    /**
     * @param  array<string>  $suggestions
     */
    public function suggestions(array $suggestions): static
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    public function tagPrefix(?string $prefix): static
    {
        $this->tagPrefix = $prefix;

        return $this;
    }

    public function tagSuffix(?string $suffix): static
    {
        $this->tagSuffix = $suffix;

        return $this;
    }

    public function isReorderable(): bool
    {
        return $this->isReorderable;
    }

    public function getSeparator(): ?string
    {
        return $this->separator;
    }

    /**
     * @return array<string>
     */
    public function getSplitKeys(): array
    {
        return $this->splitKeys;
    }

    /**
     * @return array<string>
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function getTagPrefix(): ?string
    {
        return $this->tagPrefix;
    }

    public function getTagSuffix(): ?string
    {
        return $this->tagSuffix;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'reorderable' => $this->isReorderable() ? true : null,
            'separator' => $this->separator,
            'splitKeys' => $this->splitKeys !== [] ? $this->splitKeys : null,
            'suggestions' => $this->suggestions !== [] ? $this->suggestions : null,
            'tagPrefix' => $this->tagPrefix,
            'tagSuffix' => $this->tagSuffix,
        ]);
    }
}
