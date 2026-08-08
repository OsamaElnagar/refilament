<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;

/**
 * A grid of stat cards (slice 3.1 — docs/ROADMAP.md "3.1 Dashboard +
 * StatsOverview widget").
 *
 * Mirrors Filament's `StatsOverviewWidget`
 * (filament-source/widgets/src/StatsOverviewWidget.php): a widget whose
 * stat cards come from `getStats()`. The widget serializes a self-contained
 * `stats_overview` node (docs/CONTRACT.md, "Widgets"): its layout config
 * (`heading`, `description`, `columns`) plus the serialized stat cards, the
 * same shape the (not-yet-built) dashboard shell will aggregate.
 *
 * The stat values are resolved to scalars at serialization time, so no closure
 * crosses the wire. Chart / polling (Filament's HasChartData / CanPoll) are
 * deferred — the React runtime renders this static snapshot as-is.
 */
class StatsOverviewWidget extends Widget
{
    /**
     * @var array<int, Stat>
     */
    protected array $stats = [];

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int $columns = 2;

    /**
     * @param  array<int, Stat>  $stats
     */
    public function stats(array $stats): static
    {
        $this->stats = $stats;

        return $this;
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

    public function columns(int $columns): static
    {
        $this->columns = max(1, $columns);

        return $this;
    }

    /**
     * @return array<int, Stat>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    /**
     * The wire type is `stats_overview`, not the derived
     * `stats_overview_widget` — the class name's `Widget` suffix is a
     * PHP-side concern (docs/CONTRACT.md, "Widgets").
     */
    public function getJsonType(): string
    {
        return 'stats_overview';
    }

    /**
     * Serialize the widget (docs/CONTRACT.md, "Widgets"). Omission
     * convention: `heading`, `description` and `columns` only when set / not
     * default; `stats` always requires at least one card (a widget with no
     * cards would render an empty grid, so stats is always emitted as an
     * array, empty when empty).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = parent::toArray();

        if ($this->heading !== null) {
            $payload['heading'] = $this->heading;
        }

        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        if ($this->columns !== 2) {
            $payload['columns'] = $this->columns;
        }

        $payload['stats'] = array_map(
            static fn (Stat $stat): array => $stat->toArray(),
            $this->stats,
        );

        return $payload;
    }
}
