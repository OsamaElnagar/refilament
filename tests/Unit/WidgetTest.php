<?php

declare(strict_types=1);

use Refilament\Refilament\Widgets\StatsOverviewWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;

it('serializes a bare widget with the stats_overview type', function () {
    expect(StatsOverviewWidget::make()->toArray())->toBe([
        'type' => 'stats_overview',
        'stats' => [],
    ]);
});

it('omits heading, description and columns when unset', function () {
    expect(StatsOverviewWidget::make()->toArray())->not->toHaveKeys([
        'heading',
        'description',
        'columns',
        'columnSpan',
        'columnStart',
    ]);
});

it('serializes heading, description and a non-default columns value', function () {
    expect(
        StatsOverviewWidget::make()
            ->heading('Overview')
            ->description('A snapshot')
            ->columns(4)
            ->toArray(),
    )->toBe([
        'type' => 'stats_overview',
        'heading' => 'Overview',
        'description' => 'A snapshot',
        'columns' => 4,
        'stats' => [],
    ]);
});

it('omits columns when it is the default of 2', function () {
    expect(StatsOverviewWidget::make()->columns(2)->toArray())->not->toHaveKey('columns');
});

it('clamps columns to at least 1', function () {
    expect(StatsOverviewWidget::make()->columns(0)->getColumns())->toBe(1);
    expect(StatsOverviewWidget::make()->columns(-3)->getColumns())->toBe(1);
});

it('serializes widget layout config', function () {
    expect(StatsOverviewWidget::make()->columnSpan(2)->columnStart(1)->toArray())->toBe([
        'type' => 'stats_overview',
        'columnSpan' => 2,
        'columnStart' => 1,
        'stats' => [],
    ]);
});

it('serializes stat cards with their values', function () {
    $widget = StatsOverviewWidget::make()->stats([
        Stat::make('Total posts', 42),
        Stat::make('Published', 18),
    ]);

    expect($widget->getStats())->toHaveCount(2);
    expect($widget->toArray()['stats'])->toBe([
        ['label' => 'Total posts', 'value' => 42],
        ['label' => 'Published', 'value' => 18],
    ]);
});

it('omits optional stat keys when unset', function () {
    $stat = Stat::make('Total posts', 42)->toArray();

    expect($stat)->toBe(['label' => 'Total posts', 'value' => 42]);
    expect($stat)->not->toHaveKeys(['description', 'icon', 'color']);
});

it('serializes stat description, icon and color', function () {
    expect(
        Stat::make('Total posts', 42)
            ->description('+10% this week')
            ->icon('tag')
            ->color('success')
            ->toArray(),
    )->toBe([
        'label' => 'Total posts',
        'value' => 42,
        'description' => '+10% this week',
        'icon' => 'tag',
        'color' => 'success',
    ]);
});

it('resolves closure values at serialization time', function () {
    expect(Stat::make('Total posts', fn (): int => 42)->toArray())->toBe([
        'label' => 'Total posts',
        'value' => 42,
    ]);
});

it('resolves closure descriptions, icons and colors', function () {
    expect(
        Stat::make('Total posts', 42)
            ->description(fn (): string => '+10%')
            ->icon(fn (): string => 'tag')
            ->color(fn (): string => 'info')
            ->toArray(),
    )->toBe([
        'label' => 'Total posts',
        'value' => 42,
        'description' => '+10%',
        'icon' => 'tag',
        'color' => 'info',
    ]);
});

it('drops closures that resolve to null', function () {
    expect(Stat::make('Total posts', 42)->description(fn (): ?string => null)->toArray())
        ->not->toHaveKey('description');
});

it('coerces BackedEnum values', function () {
    $status = WidgetTestStatus::Published;

    expect(Stat::make('Posts', 1)->color($status)->toArray()['color'])->toBe('published');
});

enum WidgetTestStatus: string
{
    case Published = 'published';
}
