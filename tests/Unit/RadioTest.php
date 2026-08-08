<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Radio;

it('serializes a plain radio without options', function () {
    expect(Radio::make('visibility')->toArray())->toBe([
        'type' => 'radio',
        'name' => 'visibility',
        'label' => 'Visibility',
    ]);
});

it('serializes options in the shared select shape', function () {
    $node = Radio::make('visibility')
        ->options(['public' => 'Public', 'private' => 'Private'])
        ->toArray();

    expect($node['options'])->toBe([
        ['value' => 'public', 'label' => 'Public'],
        ['value' => 'private', 'label' => 'Private'],
    ]);
});

it('serializes inline only when set', function () {
    expect(Radio::make('visibility')->toArray())->not->toHaveKey('inline');

    expect(Radio::make('visibility')->inline()->toArray()['inline'])->toBeTrue();
});

it('clamps columns to the supported 1-6 domain', function () {
    expect(Radio::make('layout')->columns(0)->toArray()['columns'])->toBe(1);
    expect(Radio::make('layout')->columns(3)->toArray()['columns'])->toBe(3);
    expect(Radio::make('layout')->columns(9)->toArray()['columns'])->toBe(6);
});

it('omits columns when unset', function () {
    expect(Radio::make('visibility')->toArray())->not->toHaveKey('columns');
});

it('inherits the shared field config', function () {
    $node = Radio::make('visibility')
        ->label('Visibility')
        ->helperText('Who can see this post')
        ->validation(['required', 'in:public,members,private'])
        ->required()
        ->toArray();

    expect($node['validation'])->toBe(['required', 'in:public,members,private']);
    expect($node['required'])->toBeTrue();
    expect($node['helperText'])->toBe('Who can see this post');
});
