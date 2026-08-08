<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

/**
 * Section layout (slice 5).
 *
 * Groups children under an optional heading and description. Deferred:
 * collapsible/collapsed, icon, aside, compact, secondary, divided, header and
 * footer actions — pure client-side or action concerns that no form needs yet.
 */
class Section extends Layout
{
    protected ?string $heading = null;

    protected ?string $description = null;

    public function getType(): string
    {
        return 'section';
    }

    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function toArray(): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'heading' => $this->getHeading(),
            'description' => $this->getDescription(),
            'schema' => $this->serializeChildren(),
        ]);
    }
}
