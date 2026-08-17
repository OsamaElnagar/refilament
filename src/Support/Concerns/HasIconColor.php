<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;

trait HasIconColor
{
    /**
     * @var string | array<string> | Closure | null
     */
    protected string|array|Closure|null $iconColor = null;

    /**
     * @param  string | array<string> | Closure | null  $color
     */
    public function iconColor(string|array|Closure|null $color): static
    {
        $this->iconColor = $color;

        return $this;
    }

    /**
     * @return string | array<string> | null
     */
    public function getIconColor(): string|array|null
    {
        $color = $this->iconColor;

        if ($color instanceof Closure) {
            $color = $this->evaluate($color);
        }

        return $color;
    }

    public function hasIconColor(): bool
    {
        return $this->iconColor !== null;
    }
}
