<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\MultiSelectFilter;
use Refilament\Refilament\Tables\SelectFilter;
use Refilament\Refilament\Tables\TernaryFilter;

it('sets the name from make() on a multi-select filter', function () {
    expect(MultiSelectFilter::make('status')->getName())->toBe('status');
});

it('sets the name from make() on a ternary filter', function () {
    expect(TernaryFilter::make('trashed')->getName())->toBe('trashed');
});

it('serializes a ternary filter with its name and type', function () {
    expect(TernaryFilter::make('trashed')->label('Trashed')->toArray())->toBe([
        'name' => 'trashed',
        'label' => 'Trashed',
        'type' => 'ternary',
    ]);
});

it('builds entry-shaped options from option()', function () {
    expect(
        MultiSelectFilter::make('status')
            ->option('Draft')
            ->option('Published', 'published')
            ->toArray()['options'],
    )->toBe([
        ['value' => 'Draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('normalizes a value-to-label map to entry-shaped options', function () {
    expect(
        MultiSelectFilter::make('status')
            ->options(['draft' => 'Draft', 'published' => 'Published'])
            ->toArray()['options'],
    )->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('normalizes entry-shaped options to the canonical entry shape', function () {
    expect(
        MultiSelectFilter::make('status')
            ->options([
                ['label' => 'Draft', 'value' => 'draft'],
                ['label' => 'Published', 'value' => 'published'],
            ])
            ->toArray()['options'],
    )->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});

it('serializes a multi-select filter as multiple', function () {
    expect(MultiSelectFilter::make('status')->toArray())->toBe([
        'name' => 'status',
        'label' => 'status',
        'type' => 'select',
        'options' => [],
        'multiple' => true,
    ]);
});

it('always reports multiple', function () {
    expect(MultiSelectFilter::make('status')->isMultiple())->toBeTrue();
});

it('matches the payload of a multiple SelectFilter', function () {
    expect(MultiSelectFilter::make('status')->toArray())
        ->toBe(SelectFilter::make('status')->multiple(true)->toArray());
});

it('serializes a configured placeholder', function () {
    expect(MultiSelectFilter::make('status')->placeholder('Pick a status')->toArray()['placeholder'])
        ->toBe('Pick a status');
});
