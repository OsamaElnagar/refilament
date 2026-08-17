<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\KeyValue;

it('returns the key_value type', function () {
    expect(KeyValue::make('meta')->getType())->toBe('key_value');
});

it('serializes a plain node', function () {
    $node = KeyValue::make('meta')->toArray();

    expect($node['type'])->toBe('key_value');
    expect($node['name'])->toBe('meta');
    expect($node)->not->toHaveKey('reorderable');
    expect($node)->not->toHaveKey('addActionLabel');
    expect($node)->not->toHaveKey('keyLabel');
    expect($node)->not->toHaveKey('valueLabel');
    expect($node)->not->toHaveKey('keyPlaceholder');
    expect($node)->not->toHaveKey('valuePlaceholder');
});

it('omits defaults-true booleans', function () {
    $node = KeyValue::make('meta')->toArray();

    expect($node)->not->toHaveKey('addable');
    expect($node)->not->toHaveKey('deletable');
    expect($node)->not->toHaveKey('editableKeys');
    expect($node)->not->toHaveKey('editableValues');
});

it('serializes the behavior flags', function () {
    $node = KeyValue::make('meta')
        ->addable(false)
        ->deletable(false)
        ->editableKeys(false)
        ->editableValues(false)
        ->reorderable()
        ->toArray();

    expect($node['addable'])->toBeFalse();
    expect($node['deletable'])->toBeFalse();
    expect($node['editableKeys'])->toBeFalse();
    expect($node['editableValues'])->toBeFalse();
    expect($node['reorderable'])->toBeTrue();
});

it('serializes labels and placeholders', function () {
    $node = KeyValue::make('meta')
        ->addActionLabel('Add a pair')
        ->keyLabel('Name')
        ->valueLabel('Setting')
        ->keyPlaceholder('e.g. theme')
        ->valuePlaceholder('e.g. dark')
        ->toArray();

    expect($node['addActionLabel'])->toBe('Add a pair');
    expect($node['keyLabel'])->toBe('Name');
    expect($node['valueLabel'])->toBe('Setting');
    expect($node['keyPlaceholder'])->toBe('e.g. theme');
    expect($node['valuePlaceholder'])->toBe('e.g. dark');
});

it('uses default labels', function () {
    expect(KeyValue::make('meta')->getAddActionLabel())->toBe('Add');
    expect(KeyValue::make('meta')->getKeyLabel())->toBe('Key');
    expect(KeyValue::make('meta')->getValueLabel())->toBe('Value');
});
