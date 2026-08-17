<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Repeater;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;

it('serializes a repeater with its row schema and options', function () {
    $node = Repeater::make('items')
        ->label('Items')
        ->defaultItems(1)
        ->minItems(1)
        ->maxItems(5)
        ->collapsible()
        ->grid(2)
        ->addActionLabel('Add row')
        ->itemLabel('Item')
        ->schema([
            TextInput::make('name')->rules(['required', 'string']),
            TextInput::make('qty')->integer(),
        ])
        ->toArray();

    expect($node['type'])->toBe('repeater');
    expect($node['name'])->toBe('items');
    expect($node['defaultItems'])->toBe(1);
    expect($node['minItems'])->toBe(1);
    expect($node['maxItems'])->toBe(5);
    expect($node['collapsible'])->toBeTrue();
    expect($node['grid'])->toBe(2);
    expect($node['addActionLabel'])->toBe('Add row');
    expect($node['itemLabel'])->toBe('Item');
    expect($node['schema'])->toHaveCount(2);
    expect($node['schema'][0]['name'])->toBe('name');
});

it('omits unset repeater options', function () {
    $node = Repeater::make('items')->schema([TextInput::make('name')])->toArray();

    expect($node)->not->toHaveKey('defaultItems')
        ->and($node)->not->toHaveKey('minItems')
        ->and($node)->not->toHaveKey('collapsible')
        ->and($node)->not->toHaveKey('grid')
        ->and($node)->not->toHaveKey('addActionLabel')
        ->and($node)->not->toHaveKey('itemLabel');
});

it('builds default rows from the row fields defaults', function () {
    $repeater = Repeater::make('items')
        ->defaultItems(2)
        ->schema([
            TextInput::make('name')->default('Anonymous'),
            TextInput::make('qty')->default(1),
        ]);

    expect($repeater->getDefault())->toBe([
        ['name' => 'Anonymous', 'qty' => 1],
        ['name' => 'Anonymous', 'qty' => 1],
    ]);
});

it('validates the repeater value as an array with min and max counts', function () {
    $rules = Repeater::make('items')
        ->minItems(1)
        ->maxItems(3)
        ->schema([TextInput::make('name')])
        ->getValidationRules();

    expect($rules)->toContain('array')
        ->and($rules)->toContain('min:1')
        ->and($rules)->toContain('max:3');
});

it('collects repeater row rules under the dotted array keys', function () {
    $schema = new Schema;

    $schema->components([
        Repeater::make('items')
            ->minItems(1)
            ->schema([
                TextInput::make('name')->rules(['required', 'string', 'max:255']),
                TextInput::make('qty')->rules(['integer', 'min:0']),
            ]),
    ]);

    $rules = $schema->getValidationRules();

    expect($rules)->toHaveKey('items')
        ->and($rules['items'])->toContain('array')
        ->and($rules)->toHaveKey('items.*.name')
        ->and($rules['items.*.name'])->toContain('required')
        ->and($rules)->toHaveKey('items.*.qty')
        ->and($rules['items.*.qty'])->toContain('min:0');

    // Row fields get readable attribute names.
    expect($schema->getValidationAttributes()['items.*.name'])->toBe('Name');
});

it('seeds initial data with the default rows', function () {
    $schema = new Schema;

    $schema->components([
        Repeater::make('items')->defaultItems(1)->schema([
            TextInput::make('name')->default('Anonymous'),
        ]),
    ]);

    expect($schema->initialData()['items'])->toBe([
        ['name' => 'Anonymous'],
    ]);
});

it('serializes the row-management toggles', function () {
    $node = Repeater::make('items')
        ->addable(false)
        ->deletable(false)
        ->cloneable()
        ->reorderable(false)
        ->itemNumbers()
        ->itemHeaders(false)
        ->schema([TextInput::make('name')])
        ->toArray();

    expect($node['addable'])->toBeFalse();
    expect($node['deletable'])->toBeFalse();
    expect($node['cloneable'])->toBeTrue();
    expect($node['reorderable'])->toBeFalse();
    expect($node['itemNumbers'])->toBeTrue();
    expect($node['itemHeaders'])->toBeFalse();
});

it('serializes the reorder modes and collapsed start', function () {
    $node = Repeater::make('items')
        ->collapsible()
        ->collapsed()
        ->reorderableWithButtons()
        ->reorderableWithDragAndDrop(false)
        ->schema([TextInput::make('name')])
        ->toArray();

    expect($node['collapsed'])->toBeTrue();
    expect($node['reorderableWithButtons'])->toBeTrue();
    expect($node['reorderableWithDragAndDrop'])->toBeFalse();
});

it('omits defaulted row-management keys', function () {
    $node = Repeater::make('items')->schema([TextInput::make('name')])->toArray();

    // Defaults-true flags are omitted; defaults-false flags are omitted.
    expect($node)->not->toHaveKey('addable')
        ->and($node)->not->toHaveKey('deletable')
        ->and($node)->not->toHaveKey('cloneable')
        ->and($node)->not->toHaveKey('reorderable')
        ->and($node)->not->toHaveKey('reorderableWithDragAndDrop')
        ->and($node)->not->toHaveKey('reorderableWithButtons')
        ->and($node)->not->toHaveKey('itemNumbers')
        ->and($node)->not->toHaveKey('itemHeaders')
        ->and($node)->not->toHaveKey('collapsed');
});

it('disables reordering entirely when reorderable is off', function () {
    $repeater = Repeater::make('items')
        ->reorderable(false)
        ->reorderableWithButtons()
        ->reorderableWithDragAndDrop();

    expect($repeater->isReorderable())->toBeFalse();
    expect($repeater->isReorderableWithButtons())->toBeFalse();
    expect($repeater->isReorderableWithDragAndDrop())->toBeFalse();
});
