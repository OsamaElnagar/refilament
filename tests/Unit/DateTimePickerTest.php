<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\DatePicker;
use Refilament\Refilament\Schemas\Components\DateTimePicker;
use Refilament\Refilament\Schemas\Components\TimePicker;

it('serializes a datetime picker node', function () {
    expect(DateTimePicker::make('published_at')->toArray())->toBe([
        'type' => 'date_time_picker',
        'name' => 'published_at',
        'label' => 'Published At',
        'inputType' => 'datetime-local',
        'format' => 'Y-m-d H:i:s',
        'displayFormat' => 'M j, Y H:i:s',
    ]);
});

it('derives the internal state format from the toggles', function () {
    expect(DateTimePicker::make('when')->getFormat())->toBe('Y-m-d H:i:s');
    expect(DateTimePicker::make('when')->seconds(false)->getFormat())->toBe('Y-m-d H:i');
    expect(DateTimePicker::make('when')->time(false)->getFormat())->toBe('Y-m-d');
    expect(DateTimePicker::make('when')->date(false)->getFormat())->toBe('H:i:s');
    expect(DateTimePicker::make('when')->date(false)->seconds(false)->getFormat())->toBe('H:i');
});

it('honors an explicit format override', function () {
    expect(DateTimePicker::make('when')->format('d/m/Y H:i')->getFormat())->toBe('d/m/Y H:i');
});

it('derives the display format from the toggles', function () {
    expect(DateTimePicker::make('when')->getDisplayFormat())->toBe('M j, Y H:i:s');
    expect(DateTimePicker::make('when')->seconds(false)->getDisplayFormat())->toBe('M j, Y H:i');
    expect(DateTimePicker::make('when')->time(false)->getDisplayFormat())->toBe('M j, Y');
    expect(DateTimePicker::make('when')->date(false)->getDisplayFormat())->toBe('H:i:s');
    expect(DateTimePicker::make('when')->date(false)->seconds(false)->getDisplayFormat())->toBe('H:i');
});

it('honors an explicit display format override', function () {
    expect(DateTimePicker::make('when')->displayFormat('MMM d, yyyy')->getDisplayFormat())->toBe('MMM d, yyyy');
});

it('serializes min and max dates and their validation rules', function () {
    $node = DateTimePicker::make('when')->minDate('2025-01-01')->maxDate('2025-12-31')->toArray();

    expect($node['minDate'])->toBe('2025-01-01');
    expect($node['maxDate'])->toBe('2025-12-31');
    expect($node['validation'])->toBe(['after_or_equal:2025-01-01', 'before_or_equal:2025-12-31']);
});

it('serializes disabled dates as strings', function () {
    $node = DateTimePicker::make('when')
        ->disabledDates(['2025-01-01', new DateTimeImmutable('2025-02-03 09:00:00')])
        ->toArray();

    expect($node['disabledDates'])->toBe(['2025-01-01', '2025-02-03 09:00:00']);
});

it('serializes the first day of week and time steps', function () {
    $node = DateTimePicker::make('when')
        ->weekStartsOnSunday()
        ->hoursStep(2)
        ->minutesStep(5)
        ->secondsStep(10)
        ->toArray();

    expect($node['firstDayOfWeek'])->toBe(7);
    expect($node['hoursStep'])->toBe(2);
    expect($node['minutesStep'])->toBe(5);
    expect($node['secondsStep'])->toBe(10);
});

it('defaults the first day of week to Monday', function () {
    expect(DateTimePicker::make('when')->getFirstDayOfWeek())->toBe(1);
});

it('omits step keys when not configured', function () {
    $node = DateTimePicker::make('when')->toArray();

    expect($node)->not->toHaveKey('hoursStep');
    expect($node)->not->toHaveKey('minutesStep');
    expect($node)->not->toHaveKey('secondsStep');
});

it('serializes timezone, locale and closeOnDateSelection', function () {
    $node = DateTimePicker::make('when')
        ->timezone('Africa/Cairo')
        ->locale('ar')
        ->closeOnDateSelection()
        ->toArray();

    expect($node['timezone'])->toBe('Africa/Cairo');
    expect($node['locale'])->toBe('ar');
    expect($node['closeOnDateSelection'])->toBe(true);
});

it('serializes a date-only picker', function () {
    $node = DatePicker::make('published_at')->toArray();

    expect($node['type'])->toBe('date_time_picker');
    expect($node['inputType'])->toBe('date');
    expect($node['format'])->toBe('Y-m-d');
    expect($node['displayFormat'])->toBe('M j, Y');
});

it('serializes a time-only picker', function () {
    $node = TimePicker::make('starts_at')->toArray();

    expect($node['type'])->toBe('date_time_picker');
    expect($node['inputType'])->toBe('time');
    expect(TimePicker::make('starts_at')->getFormat())->toBe('H:i:s');
    expect(TimePicker::make('starts_at')->seconds(false)->getFormat())->toBe('H:i');
});
