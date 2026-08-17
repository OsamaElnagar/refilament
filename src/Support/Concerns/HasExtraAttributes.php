<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;

trait HasExtraAttributes
{
    /**
     * @var array<array<mixed> | Closure>
     */
    protected array $extraAttributes = [];

    /**
     * @param  array<mixed> | Closure  $attributes
     */
    public function extraAttributes(array|Closure $attributes, bool $merge = false): static
    {
        if ($merge) {
            $this->extraAttributes[] = $attributes;
        } else {
            $this->extraAttributes = [$attributes];
        }

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getExtraAttributes(): array
    {
        $attributes = [];

        foreach ($this->extraAttributes as $extraAttributes) {
            $attributes = [...$attributes, ...$this->evaluate($extraAttributes)];
        }

        return $attributes;
    }

    public function hasExtraAttributes(): bool
    {
        return ! empty($this->extraAttributes);
    }
}
