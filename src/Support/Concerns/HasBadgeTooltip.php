<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;

trait HasBadgeTooltip
{
    protected string|Closure|null $badgeTooltip = null;

    public function badgeTooltip(string|Closure|null $tooltip): static
    {
        $this->badgeTooltip = $tooltip;

        return $this;
    }

    public function getBadgeTooltip(?string $badge = null): ?string
    {
        return $this->evaluate($this->badgeTooltip, [
            'badge' => $badge,
        ]);
    }
}
