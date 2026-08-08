<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

use Closure;
use Refilament\Refilament\Schemas\Components\Component;
use Refilament\Refilament\Schemas\Schema;

/**
 * A chart widget (slice 3.2 — docs/ROADMAP.md "3.2 Charts widget").
 *
 * @phpstan-type ChartWidgetData array{labels: array<int, string>, datasets: array<int, array{data: array<int, int|float>, label?: string, color?: string}>}
 *
 * Mirrors Filament's `ChartWidget`
 * (filament-source/widgets/src/ChartWidget.php): a widget whose chart data
 * comes from `getData()` and whose presentation comes from a small set of
 * config methods (`heading`, `description`, `color`, `options`, filters).
 *
 * The render model is honest request/response — never a fake Livewire poll.
 * A chart is serialized as a static snapshot by default (its resolved data +
 * options in one JSON node, zero round trips). When a widget declares
 * `pollingInterval()` and/or `filters(Schema)`, the node carries its `id` and
 * the React runtime refetches the typed data endpoint
 * (`GET /refilament/widget/{id}/data`) — the widget is rebuilt per request
 * (resolver registry), filter values flow in as `filter[...]` params, and the
 * `data()` closure re-resolves server-side. Closures are resolved to data
 * arrays at serialization time, never shipped.
 *
 * The shipped data uses the same shape Filament hands Chart.js
 * ({ `labels`, `datasets`: [{ data }] }), so a Filament `getData()` port
 * carries over without reshaping.
 */
abstract class ChartWidget extends Widget
{
    /**
     * Chart.js-style data map:
     * `['labels' => list<string>, 'datasets' => list<array{data: list<int|float>, label?: string, color?: string}>]`.
     *
     * A closure receives the current filter values as its first argument
     * (`$filterData`); a zero-arg closure simply ignores it.
     *
     * @var Closure(array<string, mixed>): ChartWidgetData|ChartWidgetData|null
     */
    protected mixed $chartData = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $options = null;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected string $color = 'primary';

    protected int $height = 300;

    /**
     * When set, the React runtime refetches the widget's data endpoint every
     * N seconds (mirrors Filament's `CanPoll::$pollingInterval` — ours is a
     * client timer over the typed endpoint, not a Livewire poll). The refetch
     * carries the current filter values.
     */
    protected ?int $pollingInterval = null;

    /**
     * The filter form re-running the data closure per request (mirrors
     * Filament's `HasFiltersSchema::filtersSchema()`). Its fields serialize
     * onto the node; the client sends their values as `filter[<name>]=...`
     * params to the data endpoint, where they reach the `data()` closure.
     */
    protected ?Schema $filtersSchema = null;

    /**
     * Register the chart data. A closure is resolved at serialization time
     * (so it may query the database) and receives the current filter values
     * as `$filterData` when the widget declares a filter form; an array is
     * shipped as-is. The value shape is the Chart.js-style
     * `['labels' => [ ... ], 'datasets' => [ ['data' => [ ... ]] ]]` map —
     * the same shape Filament's `getData()` returns (docs/CONTRACT.md,
     * "Widgets").
     *
     * @param  Closure(array<string, mixed>): ChartWidgetData|ChartWidgetData  $data
     */
    public function data(Closure|array $data): static
    {
        $this->chartData = $data;

        return $this;
    }

    /**
     * Raw Chart.js-style options (legend, scales, ...) passed through to the
     * renderer verbatim. Pure data — never closures.
     *
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

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

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Poll the data endpoint every N seconds. Mirrors Filament's
     * `CanPoll::$pollingInterval`; ours is a deliberate client-side timer
     * over the typed endpoint, so the server stays stateless.
     */
    public function pollingInterval(?int $seconds): static
    {
        $this->pollingInterval = $seconds;

        return $this;
    }

    public function getPollingInterval(): ?int
    {
        return $this->pollingInterval;
    }

    /**
     * Declare the filter form this chart's data depends on. Its field values
     * reach the `data()` closure as `$filterData` on every render (dashboard
     * snapshot and data endpoint alike). Mirrors Filament's
     * `HasFiltersSchema::filtersSchema()`.
     */
    public function filters(Schema $schema): static
    {
        $this->filtersSchema = $schema;

        return $this;
    }

    public function getFiltersSchema(): ?Schema
    {
        return $this->filtersSchema;
    }

    /**
     * Resolve the chart data. A closure receives the current filter values
     * (`$filterData`) so a chart can narrow its query per request; an array
     * is shipped as-is. Closures declared with no parameters simply ignore
     * the extra argument, so pre-filter charts keep working untouched.
     *
     * @param  array<string, mixed>  $filterData
     * @return ChartWidgetData|array{}
     */
    public function getData(array $filterData = []): array
    {
        if ($this->chartData instanceof Closure) {
            return ($this->chartData)($filterData);
        }

        return $this->chartData ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function isEmpty(): bool
    {
        return $this->getData() === [];
    }

    /**
     * The wire chart type ('bar' | 'line' | 'pie' | ...) — earlier maps to
     * the same keys Filament's ChartWidget uses. Subclasses provide it.
     */
    abstract public function getChartType(): string;

    /**
     * The wire type joins the `chart_` prefix with the chart type, so the
     * registry keys read `chart_bar`, `chart_line`, `chart_pie`, ... — a
     * single `chart_` renderer maps over the chart type at doc-center.
     */
    public function getJsonType(): string
    {
        return 'chart_'.$this->getChartType();
    }

    /**
     * Serialize the chart widget (docs/CONTRACT.md, "Widgets"). Omission
     * convention: `heading`, `description`, `options` only when set / not
     * default; `data` always emitted (empty array when empty, so the
     * renderer can show its empty state). `columnSpan`/`columnStart` come from
     * the Widget base.
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

        if ($this->color !== 'primary') {
            $payload['color'] = $this->color;
        }

        if ($this->height !== 300) {
            $payload['height'] = $this->height;
        }

        if ($this->options !== null) {
            $payload['options'] = $this->options;
        }

        // The live-data surface (slice 3.2): only widgets that opt in carry
        // the `id` (the typed data endpoint's address), `pollingInterval` and
        // the serialized filter form — a static snapshot stays minimal.
        if ($this->pollingInterval !== null || $this->filtersSchema !== null) {
            $payload['id'] = $this->getWidgetId();
        }

        if ($this->pollingInterval !== null) {
            $payload['pollingInterval'] = $this->pollingInterval;
        }

        if ($this->filtersSchema !== null) {
            $payload['filters'] = [
                'schema' => array_map(
                    static fn (Component $component): array => $component->toArray(),
                    $this->filtersSchema->getComponents(),
                ),
                'data' => $this->filtersSchema->initialData(),
            ];
        }

        // The dashboard's initial snapshot always resolves with the filter
        // form's **defaults** — never the ambient request's query string — so
        // the snapshot always matches the client's initial filter state (and
        // serialization stays deterministic). Real filter values only ever
        // enter through the typed data endpoint's `filter[...]` params
        // (WidgetDataController), never here.
        $payload['data'] = $this->getData($this->getFilterDefaults());

        return $payload;
    }

    /**
     * The filter form's default values — the only values a rendered snapshot
     * resolves with. The typed data endpoint passes the client-sent
     * `filter[...]` params straight to `getData()`, so this never reads the
     * request.
     *
     * @return array<string, mixed>
     */
    protected function getFilterDefaults(): array
    {
        return $this->filtersSchema?->initialData() ?? [];
    }
}
