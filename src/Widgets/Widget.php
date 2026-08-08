<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

use Illuminate\Support\Str;

/**
 * Base widget (slice 3.1 — docs/ROADMAP.md "3.1 Dashboard + StatsOverview
 * widget").
 *
 * A widget is a self-contained, request-rendered display unit. Where Filament
 * widgets are long-lived Livewire components re-executing PHP closures on
 * every poll, ours are static data snapshots: the widget serializes a JSON
 * node (docs/CONTRACT.md, "Widgets") that the React runtime renders with zero
 * server round trips. Closures are resolved to values at serialization time,
 * never shipped — so the "live"/"poll" portion of a widget is deliberately
 * out of v1's scope.
 *
 * The layout metadata (columnSpan / columnStart) mirrors Filament's Widget
 * surface. It is pure data — it describes where the widget sits in a grid the
 * (not-yet-built) panel shell will host, so it never affects what the widget
 * renders.
 */
abstract class Widget
{
    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 1;

    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnStart = [];

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  int|string|array<string, int|null>  $columnSpan
     */
    public function columnSpan(int|string|array $columnSpan): static
    {
        $this->columnSpan = $columnSpan;

        return $this;
    }

    /**
     * @param  int|string|array<string, int|null>  $columnStart
     */
    public function columnStart(int|string|array $columnStart): static
    {
        $this->columnStart = $columnStart;

        return $this;
    }

    /**
     * @return int|string|array<string, int|null>
     */
    public function getColumnSpan(): int|string|array
    {
        return $this->columnSpan;
    }

    /**
     * @return int|string|array<string, int|null>
     */
    public function getColumnStart(): int|string|array
    {
        return $this->columnStart;
    }

    /**
     * Serialize the widget definition (docs/CONTRACT.md, "Widgets"). The
     * `type` key is the snake_case widget name the React renderer regdistry
     * maps to a component — never a hardcoded switch server-side. Subclasses
     * add their own keys to this base.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'type' => $this->getJsonType(),
        ];

        if ($this->columnSpan !== 1) {
            $payload['columnSpan'] = $this->columnSpan;
        }

        if ($this->columnStart !== []) {
            $payload['columnStart'] = $this->columnStart;
        }

        return $payload;
    }

    /**
     * The snake_case widget type, derived from the class name
     * ('StatsOverviewWidget' => 'stats_overview'). Overridable for custom
     * types.
     */
    public function getJsonType(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
