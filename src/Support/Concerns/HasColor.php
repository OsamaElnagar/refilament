<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;

trait HasColor
{
    /**
     * @var string | array<string> | Closure | null
     */
    protected string|array|Closure|null $color = null;

    /**
     * @var string | array<string> | Closure | null
     */
    protected string|array|Closure|null $defaultColor = null;

    /**
     * @param  string | array<string> | Closure | null  $color
     */
    public function color(string|array|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @param  string | array<string> | Closure | null  $color
     */
    public function defaultColor(string|array|Closure|null $color): static
    {
        $this->defaultColor = $color;

        return $this;
    }

    /**
     * @return string | array<string> | null
     */
    public function getColor(): string|array|null
    {
        $color = $this->color;

        if ($color instanceof Closure) {
            $color = $this->evaluate($color);
        }

        return $color ?? $this->evaluate($this->defaultColor);
    }

    public function hasColor(): bool
    {
        return $this->color !== null || $this->defaultColor !== null;
    }
}
