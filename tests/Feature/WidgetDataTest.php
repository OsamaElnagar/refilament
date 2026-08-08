<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Widgets\BarChartWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;

beforeEach(function () {
    $refilament = app(Refilament::class);

    // A chart whose data closure echoes the filter values back, so the test
    // can assert both the fresh resolution and the filter plumbing.
    $refilament->registerWidgetResolver(
        'test-chart',
        static fn (): BarChartWidget => BarChartWidget::make()->data(
            static fn (array $filterData): array => [
                'labels' => ['A'],
                'datasets' => [['data' => [(int) ($filterData['x'] ?? 0)]]],
            ],
        ),
    );

    $refilament->registerWidgetResolver(
        'test-stats',
        static fn (): StatsOverviewWidget => StatsOverviewWidget::make()->stats([
            Stat::make('Posts', static fn (): int => 1),
        ]),
    );
});

it('serves fresh data from a registered widget', function () {
    $this->getJson('/refilament/widget/test-chart/data')
        ->assertOk()
        ->assertJsonPath('data.labels', ['A'])
        ->assertJsonPath('data.datasets.0.data', [0]);
});

it('passes filter params into the data closure', function () {
    $this->getJson('/refilament/widget/test-chart/data?filter[x]=5')
        ->assertOk()
        ->assertJsonPath('data.datasets.0.data', [5]);
});

it('rebuilds the widget per request (no state between requests)', function () {
    $this->getJson('/refilament/widget/test-chart/data?filter[x]=3')->assertOk();

    // A second request sees only what it sent — nothing persisted.
    $this->getJson('/refilament/widget/test-chart/data')
        ->assertOk()
        ->assertJsonPath('data.datasets.0.data', [0]);
});

it('rejects an unknown widget', function () {
    $this->getJson('/refilament/widget/nope/data')->assertNotFound();
});

it('rejects a widget that does not expose live data', function () {
    $this->getJson('/refilament/widget/test-stats/data')->assertStatus(422);
});
