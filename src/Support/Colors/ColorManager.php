<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Colors;

use Closure;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;

/**
 * The named-color registry (mirrors Filament's `Support\Colors\ColorManager`,
 * minus the Blade CSS-generation surface — our panel renders to JSON, so the
 * palette lookups here are what a builder calls when a component needs a
 * concrete color set from a name like `'primary'` or `'red'`).
 *
 * `register()` accepts a map of name => palette (or a closure returning one);
 * string values are expanded to a full 50→950 palette via `Color::generatePalette()`.
 * Colors are cached on first `getColors()`, and callers can override / add /
 * remove shades of a named palette.
 */
class ColorManager
{
    use EvaluatesClosures;

    public const array DEFAULT_COLORS = [
        'danger' => Color::Red,
        'gray' => Color::Zinc,
        'info' => Color::Blue,
        'primary' => Color::Amber,
        'success' => Color::Green,
        'warning' => Color::Amber,
    ];

    /**
     * @var array<array<string, array<int, string> | string> | Closure>
     */
    protected array $colors = [];

    /**
     * @var array<string, array<int, string>> | null
     */
    protected ?array $cachedColors = null;

    /**
     * @var array<string, array<int>>
     */
    protected array $overridingShades = [];

    /**
     * @var array<string, array<int>>
     */
    protected array $addedShades = [];

    /**
     * @var array<string, array<int>>
     */
    protected array $removedShades = [];

    /**
     * @param  array<string, array<int, string> | string> | Closure  $colors
     */
    public function register(array|Closure $colors): static
    {
        $this->colors[] = $colors;

        $this->cachedColors = null;

        return $this;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getColors(): array
    {
        if (isset($this->cachedColors)) {
            return $this->cachedColors;
        }

        $all = static::DEFAULT_COLORS;

        foreach ($this->colors as $colors) {
            $colors = $this->evaluate($colors);

            if (! is_array($colors)) {
                continue;
            }

            foreach ($colors as $name => $color) {
                if (is_string($color)) {
                    $color = Color::generatePalette($color);
                } else {
                    $color = array_map(
                        fn (string $color): string => Color::convertToOklch($color),
                        $color,
                    );
                }

                $all[$name] = $color;
            }
        }

        return $this->cachedColors = $all;
    }

    /**
     * @return ?array<int, string>
     */
    public function getColor(string $color): ?array
    {
        return $this->getColors()[$color] ?? null;
    }

    /**
     * @param  array<int>  $shades
     */
    public function overrideShades(string $alias, array $shades): void
    {
        $this->overridingShades[$alias] = $shades;
    }

    /**
     * @return array<int> | null
     */
    public function getOverridingShades(string $alias): ?array
    {
        return $this->overridingShades[$alias] ?? null;
    }

    /**
     * @param  array<int>  $shades
     */
    public function addShades(string $alias, array $shades): void
    {
        $this->addedShades[$alias] = $shades;
    }

    /**
     * @return array<int> | null
     */
    public function getAddedShades(string $alias): ?array
    {
        return $this->addedShades[$alias] ?? null;
    }

    /**
     * @param  array<int>  $shades
     */
    public function removeShades(string $alias, array $shades): void
    {
        $this->removedShades[$alias] = $shades;
    }

    /**
     * @return array<int> | null
     */
    public function getRemovedShades(string $alias): ?array
    {
        return $this->removedShades[$alias] ?? null;
    }
}
