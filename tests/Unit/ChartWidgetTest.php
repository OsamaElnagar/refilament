<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Widgets\BarChartWidget;

it('omits the live-data surface keys by default', function () {
    $array = BarChartWidget::make()
        ->data(['labels' => [], 'datasets' => []])
        ->toArray();

    expect($array)->not->toHaveKeys(['id', 'pollingInterval', 'filters']);
});

it('serializes pollingInterval and the filter form when opted in', function () {
    $array = BarChartWidget::make()
        ->data(static fn (): array => ['labels' => [], 'datasets' => []])
        ->pollingInterval(10)
        ->filters(Schema::make()->components([
            Select::make('range')
                ->label('Published within')
                ->options(['all' => 'Anytime', '7' => 'Last 7 days'])
                ->default('all'),
        ]))
        ->toArray();

    expect($array['id'])->toBe('bar-chart');
    expect($array['pollingInterval'])->toBe(10);
    expect($array['filters'])->toHaveKeys(['schema', 'data']);
    expect($array['filters']['data'])->toBe(['range' => 'all']);
    expect($array['filters']['schema'][0]['type'])->toBe('select');
    expect($array['filters']['schema'][0]['options'])->toBe([
        ['value' => 'all', 'label' => 'Anytime'],
        ['value' => '7', 'label' => 'Last 7 days'],
    ]);
});

it('passes filter data into the data closure', function () {
    $widget = BarChartWidget::make()->data(
        static fn (array $filterData): array => [
            'labels' => [],
            'datasets' => [['data' => [(int) ($filterData['x'] ?? 0)]]],
        ],
    );

    expect($widget->getData(['x' => 5])['datasets'][0]['data'])->toBe([5]);
    expect($widget->getData()['datasets'][0]['data'])->toBe([0]);
});

it('keeps zero-arg closures working when called with filter data', function () {
    $widget = BarChartWidget::make()->data(
        static fn (): array => ['labels' => [], 'datasets' => []],
    );

    expect($widget->getData(['x' => 1]))->toBe(['labels' => [], 'datasets' => []]);
});
