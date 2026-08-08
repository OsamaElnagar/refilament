<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\CheckboxList;

it('serializes a plain checkbox list without options', function () {
    expect(CheckboxList::make('features')->toArray())->toBe([
        'type' => 'checkbox_list',
        'name' => 'features',
        'label' => 'Features',
    ]);
});

it('serializes options in the shared select shape', function () {
    $node = CheckboxList::make('features')
        ->options(['search' => 'Search', 'pagination' => 'Pagination'])
        ->toArray();

    expect($node['options'])->toBe([
        ['value' => 'search', 'label' => 'Search'],
        ['value' => 'pagination', 'label' => 'Pagination'],
    ]);
});

it('serializes searchable only when set', function () {
    expect(CheckboxList::make('features')->toArray())->not->toHaveKey('searchable');

    expect(CheckboxList::make('features')->searchable()->toArray()['searchable'])->toBeTrue();
});

it('serializes bulkToggleable only when set', function () {
    expect(CheckboxList::make('features')->toArray())->not->toHaveKey('bulkToggleable');

    expect(CheckboxList::make('features')->bulkToggleable()->toArray()['bulkToggleable'])->toBeTrue();
});

it('clamps columns to the supported 1-6 domain', function () {
    expect(CheckboxList::make('layout')->columns(0)->toArray()['columns'])->toBe(1);
    expect(CheckboxList::make('layout')->columns(3)->toArray()['columns'])->toBe(3);
    expect(CheckboxList::make('layout')->columns(9)->toArray()['columns'])->toBe(6);
});

it('omits columns when unset', function () {
    expect(CheckboxList::make('features')->toArray())->not->toHaveKey('columns');
});

it('serializes per-option descriptions', function () {
    $node = CheckboxList::make('features')
        ->descriptions(['search' => 'Instant search across posts'])
        ->toArray();

    expect($node['descriptions'])->toBe(['search' => 'Instant search across posts']);
});

it('omits descriptions when unset', function () {
    expect(CheckboxList::make('features')->toArray())->not->toHaveKey('descriptions');
});

it('inherits the shared field config', function () {
    $node = CheckboxList::make('features')
        ->label('Features')
        ->helperText('Pick the features to enable')
        ->required()
        ->toArray();

    expect($node['validation'])->toBe(['required']);
    expect($node['required'])->toBeTrue();
    expect($node['helperText'])->toBe('Pick the features to enable');
});

it('serializes the options with a default label headline', function () {
    expect(CheckboxList::make('tags')->getLabel())->toBe('Tags');
});
