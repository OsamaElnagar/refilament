<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\TextInput;

it('serializes a plain text input without an inputType', function () {
    expect(TextInput::make('title')->toArray())->toBe([
        'type' => 'text_input',
        'name' => 'title',
        'label' => 'Title',
    ]);
});

it('serializes an email input', function () {
    $node = TextInput::make('email')->email()->toArray();

    expect($node['inputType'])->toBe('email');
    expect($node['validation'])->toBe(['email']);
});

it('serializes a numeric input with min and max values', function () {
    $node = TextInput::make('quantity')->numeric()->minValue(1)->maxValue(100)->toArray();

    expect($node['inputType'])->toBe('number');
    expect($node['minValue'])->toBe(1);
    expect($node['maxValue'])->toBe(100);
    expect($node['validation'])->toBe(['numeric']);
});

it('serializes an integer input', function () {
    $node = TextInput::make('count')->integer()->toArray();

    expect($node['inputType'])->toBe('number');
    expect($node['validation'])->toBe(['integer']);
});

it('serializes a password input with revealable', function () {
    $node = TextInput::make('password')->password()->revealable()->toArray();

    expect($node['inputType'])->toBe('password');
    expect($node['revealable'])->toBeTrue();
});

it('serializes a copyable input with a copy message', function () {
    $node = TextInput::make('token')->copyable(copyMessage: 'Token copied!')->toArray();

    expect($node['copyable'])->toBeTrue();
    expect($node['copyMessage'])->toBe('Token copied!');
});

it('serializes a tel input and keeps the regex only when set', function () {
    $node = TextInput::make('phone')->tel()->toArray();

    expect($node['inputType'])->toBe('tel');
    expect($node)->not->toHaveKey('telRegex');

    $node = TextInput::make('phone')->tel()->telRegex('/^[0-9]{10}$/')->toArray();

    expect($node['telRegex'])->toBe('/^[0-9]{10}$/');
});

it('serializes a url input', function () {
    $node = TextInput::make('website')->url()->toArray();

    expect($node['inputType'])->toBe('url');
    expect($node['validation'])->toBe(['url']);
});

it('appends the current_password rule', function () {
    $node = TextInput::make('current')->currentPassword()->toArray();

    expect($node['validation'])->toBe(['current_password']);
});

it('uses the explicit type override', function () {
    $node = TextInput::make('date')->type('date')->toArray();

    expect($node['inputType'])->toBe('date');
});

it('serializes a step for numeric inputs', function () {
    $node = TextInput::make('quantity')->numeric()->minValue(0)->maxValue(120)->step(5)->toArray();

    expect($node['inputType'])->toBe('number');
    expect($node['step'])->toBe(5);
    expect(TextInput::make('quantity')->toArray())->not->toHaveKey('step');
});

it('follows Filament input type precedence', function () {
    $node = TextInput::make('mixed')->password()->email()->toArray();

    expect($node['inputType'])->toBe('email');
});

it('uses the default tel regex when none is configured', function () {
    expect(TextInput::make('phone')->getTelRegex())->toContain('^[+]*');
});

it('serializes a computed expression as data', function () {
    $node = TextInput::make('total')
        ->numeric()
        ->readOnly()
        ->computed('quantity * unit_price')
        ->toArray();

    expect($node['computed'])->toBe('quantity * unit_price');
    expect($node['readOnly'])->toBeTrue();
    expect($node['inputType'])->toBe('number');
});

it('omits the computed key when unset', function () {
    expect(TextInput::make('total')->toArray())->not->toHaveKey('computed');
});

it('rejects an empty computed expression', function () {
    expect(fn () => TextInput::make('total')->computed('   '))->toThrow(LogicException::class);
});
