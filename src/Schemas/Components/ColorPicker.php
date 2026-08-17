<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Color picker field — a text input with a preview swatch and a popover
 * color picker.
 *
 * Mirrors Filament's config API where it is pure data. `format()` selects the
 * serialized color format (`hex`, `hsl`, `rgb`, `rgba`; default `hex`), with
 * `hex()/hsl()/rgb()/rgba()` conveniences. The React runtime owns the picker
 * interaction and emits the value in the chosen format.
 *
 * Deferred for v1: prefix/suffix affixes, extra input attributes.
 */
class ColorPicker extends Component
{
    protected string $format = 'hex';

    public function getType(): string
    {
        return 'color_picker';
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function hex(): static
    {
        return $this->format('hex');
    }

    public function hsl(): static
    {
        return $this->format('hsl');
    }

    public function rgb(): static
    {
        return $this->format('rgb');
    }

    public function rgba(): static
    {
        return $this->format('rgba');
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'format' => $this->format,
        ]);
    }
}
