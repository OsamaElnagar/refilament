<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\ToggleButtons;

it('returns the toggle_buttons type', function () {
    expect(ToggleButtons::make('status')->getType())->toBe('toggle_buttons');
});

it('serializes a plain node with only the type and name', function () {
    $node = ToggleButtons::make('status')->toArray();

    expect($node['type'])->toBe('toggle_buttons');
    expect($node['name'])->toBe('status');
    expect($node)->not->toHaveKey('multiple');
    expect($node)->not->toHaveKey('inline');
    expect($node)->not->toHaveKey('grouped');
    expect($node)->not->toHaveKey('hiddenButtonLabels');
    expect($node)->not->toHaveKey('icons');
    expect($node)->not->toHaveKey('colors');
    expect($node)->not->toHaveKey('tooltips');
});

it('serializes options and multiple', function () {
    $node = ToggleButtons::make('status')
        ->options(['draft' => 'Draft', 'published' => 'Published'])
        ->multiple()
        ->toArray();

    expect($node['options'])->toBe([
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
    expect($node['multiple'])->toBeTrue();
    expect($node)->not->toHaveKey('inline');
    expect($node)->not->toHaveKey('grouped');
});

it('serializes the display variants', function () {
    $node = ToggleButtons::make('status')
        ->inline()
        ->grouped()
        ->hiddenButtonLabels()
        ->toArray();

    expect($node['inline'])->toBeTrue();
    expect($node['grouped'])->toBeTrue();
    expect($node['hiddenButtonLabels'])->toBeTrue();
});

it('serializes per-option icons, colors and tooltips', function () {
    $node = ToggleButtons::make('status')
        ->icons(['draft' => 'pencil', 'published' => 'check'])
        ->colors(['draft' => 'warning', 'published' => 'success'])
        ->tooltips(['draft' => 'Not visible yet'])
        ->toArray();

    expect($node['icons'])->toBe(['draft' => 'pencil', 'published' => 'check']);
    expect($node['colors'])->toBe(['draft' => 'warning', 'published' => 'success']);
    expect($node['tooltips'])->toBe(['draft' => 'Not visible yet']);
});

it('boolean sets labelled 1/0 options with success/danger colors', function () {
    $node = ToggleButtons::make('active')->boolean('Enabled', 'Disabled')->toArray();

    expect($node['options'])->toBe([
        ['value' => '1', 'label' => 'Enabled'],
        ['value' => '0', 'label' => 'Disabled'],
    ]);
    expect($node['colors'])->toBe(['1' => 'success', '0' => 'danger']);
});

it('boolean uses default labels and keeps int-like string keys in json', function () {
    $node = ToggleButtons::make('active')->boolean()->toArray();

    expect($node['options'])->toBe([
        ['value' => '1', 'label' => 'Yes'],
        ['value' => '0', 'label' => 'No'],
    ]);
    expect(json_decode(json_encode($node['options']), true))->toBe([
        ['value' => '1', 'label' => 'Yes'],
        ['value' => '0', 'label' => 'No'],
    ]);
});

it('omits per-option maps when empty', function () {
    $node = ToggleButtons::make('status')
        ->options(['a' => 'A'])
        ->toArray();

    expect($node)->not->toHaveKey('icons');
    expect($node)->not->toHaveKey('colors');
    expect($node)->not->toHaveKey('tooltips');
});
