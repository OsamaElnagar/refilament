<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

/**
 * Multi-select filter — allows selecting multiple values from a set of options.
 *
 * @deprecated Use `SelectFilter` with the `multiple()` method instead.
 */
class MultiSelectFilter extends SelectFilter
{
    public function isMultiple(): bool
    {
        return true;
    }

    /**
     * Append a single option, keyed by an explicit value when given
     * (otherwise the label is the value).
     */
    public function option(string $label, ?string $value = null): static
    {
        $this->options[$value ?? $label] = $label;

        return $this;
    }

    /**
     * Set the filter's options. Accepts either a list of `{label, value}`
     * entries (the shape `option()` produces and the client consumes) or a
     * value => label map — both normalized onto the parent's value => label
     * contract so the inherited `toArray()` ships the entry shape the client
     * renders.
     *
     * @param  array<int, array{label: string, value: string}>|array<string, string>  $options
     */
    public function options(array $options): static
    {
        $normalized = [];

        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $normalized[(string) $label['value']] = (string) $label['label'];
            } else {
                $normalized[(string) $value] = $label;
            }
        }

        $this->options = $normalized;

        return $this;
    }
}
