<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use BackedEnum;
use Closure;

trait HasIcon
{
    protected string|BackedEnum|Closure|false|null $icon = null;

    public function icon(string|BackedEnum|Closure|null $icon): static
    {
        $this->icon = filled($icon) ? $icon : false;

        return $this;
    }

    public function getIcon(string|BackedEnum|null $default = null): string|BackedEnum|null
    {
        $icon = $this->icon;

        if ($icon instanceof Closure) {
            $icon = $this->evaluate($icon);
        }

        if ($icon === false) {
            return null;
        }

        return $icon ?? $default;
    }

    public function hasIcon(): bool
    {
        return $this->icon !== null && $this->icon !== false;
    }
}
