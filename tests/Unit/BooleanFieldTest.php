<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Checkbox;
use Refilament\Refilament\Schemas\Components\Toggle;
use Refilament\Refilament\Schemas\Schema;

it('serializes a plain checkbox with a false default and boolean rule', function () {
    expect(Checkbox::make('featured')->toArray())->toBe([
        'type' => 'checkbox',
        'name' => 'featured',
        'label' => 'Featured',
        'default' => false,
        'validation' => ['boolean'],
    ]);
});

it('serializes a plain toggle with a false default and boolean rule', function () {
    expect(Toggle::make('enabled')->toArray())->toBe([
        'type' => 'toggle',
        'name' => 'enabled',
        'label' => 'Enabled',
        'default' => false,
        'validation' => ['boolean'],
    ]);
});

it('serializes an explicit default', function () {
    $node = Toggle::make('allow_comments')->default(true)->toArray();

    expect($node['default'])->toBeTrue();
});

it('serializes inline only when set', function () {
    expect(Checkbox::make('featured')->toArray())->not->toHaveKey('inline');

    $node = Checkbox::make('featured')->inline()->toArray();

    expect($node['inline'])->toBeTrue();
});

it('adds the accepted rule for consent-required boxes', function () {
    $node = Checkbox::make('terms')->accepted()->toArray();

    expect($node['validation'])->toBe(['boolean', 'accepted']);
});

it('keeps the boolean rule when a custom rule list is set', function () {
    $node = Toggle::make('enabled')->rules(['accepted'])->toArray();

    expect($node['validation'])->toBe(['boolean', 'accepted']);
});

it('contributes a false default to the form initial data', function () {
    $data = Schema::make()->components([Checkbox::make('terms')])->initialData();

    expect($data)->toBe(['terms' => false]);
});

it('inherits the shared field config', function () {
    $node = Checkbox::make('featured')
        ->label('Featured post')
        ->helperText('Highlight this post')
        ->disabled()
        ->columnSpan(2)
        ->toArray();

    expect($node['label'])->toBe('Featured post');
    expect($node['helperText'])->toBe('Highlight this post');
    expect($node['disabled'])->toBeTrue();
    expect($node['columnSpan'])->toBe(2);
});
