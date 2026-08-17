<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;
use Refilament\Refilament\Support\Enums\IconSize;

trait HasIconSize
{
    protected IconSize|string|Closure|null $iconSize = null;

    public function iconSize(IconSize|string|Closure|null $size): static
    {
        $this->iconSize = $size;

        return $this;
    }

    public function getIconSize(): IconSize|string|null
    {
        return $this->evaluate($this->iconSize);
    }

    public function hasIconSize(): bool
    {
        return $this->iconSize !== null;
    }
}
