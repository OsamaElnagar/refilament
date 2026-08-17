<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Closure;
use Refilament\Refilament\Tables\Column;

/**
 * Select column — an inline-editable dropdown rendered as a native `<select>`
 * (mirrors `Filament\Tables\Columns\SelectColumn`). Choosing an option posts
 * the new value through the record-column update endpoint (a stateless
 * request/response). The cell state is the option value; while editable the
 * client shows a `<select>` listing the options (shipping the raw value), and
 * `getOptionLabel()` maps a value to its label for the read-only fallback.
 *
 * @method static \Refilament\Refilament\Tables\Columns\SelectColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\SelectColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\SelectColumn toggleable(bool $condition = true)
 */
class SelectColumn extends Column
{
    /** @var array<string, string>|Closure value => label */
    protected array|Closure $options = [];

    /** @var array<int, mixed> values that may not be selected */
    protected array $disabledOptions = [];

    public function configure(): static
    {
        return $this->editable();
    }

    /**
     * The selectable options — a `value => label` map, or a closure returning
     * one (evaluated at serialization time, stateless).
     *
     * @param  array<string, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    /**
     * Prevent a single option (or several) from being selected — rendered
     * disabled in the `<select>`. Mirrors Filament's `disabledOption()`.
     */
    public function disabledOption(mixed $option): static
    {
        $this->disabledOptions[] = $option;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $options
     */
    public function disabledOptions(array $options): static
    {
        $this->disabledOptions = array_values($options);

        return $this;
    }

    public function isOptionDisabled(mixed $value): bool
    {
        return in_array($value, $this->disabledOptions, true);
    }

    public function getOptionLabel(mixed $state): ?string
    {
        if (is_string($state) || is_int($state)) {
            $label = $this->getOptions()[$state] ?? null;

            if (is_string($label)) {
                return $label;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        $options = $this->getOptions();

        $payload = parent::toArray();
        $payload['kind'] = 'select';
        $payload['options'] = array_map(
            fn (string $label, mixed $value): array => [
                'value' => (string) $value,
                'label' => $label,
                ...($this->isOptionDisabled($value) ? ['isDisabled' => true] : []),
            ],
            $options,
            array_keys($options),
        );

        if ($this->getPlaceholder() !== null) {
            $payload['placeholder'] = $this->getPlaceholder();
        }

        return $payload;
    }

    public function serializeCell(mixed $record): mixed
    {
        $state = $this->getStateFor($record);

        if ($this->isEditable()) {
            return $state;
        }

        return $this->getOptionLabel($state) ?? $state;
    }
}
