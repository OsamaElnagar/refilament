<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\TextInput;

it('serializes whenTruthy', function () {
    $node = TextInput::make('bio')->whenTruthy('show_author')->toArray();

    expect($node['whenTruthy'])->toBe(['show_author']);
});

it('serializes whenFalsy', function () {
    $node = TextInput::make('note')->whenFalsy(['allow_comments'])->toArray();

    expect($node['whenFalsy'])->toBe(['allow_comments']);
});

it('accepts a single field name for whenTruthy and whenFalsy', function () {
    $node = TextInput::make('n')->whenTruthy('a')->whenFalsy('b')->toArray();

    expect($node['whenTruthy'])->toBe(['a']);
    expect($node['whenFalsy'])->toBe(['b']);
});

it('wraps a string argument in an array', function () {
    $node = TextInput::make('n')->whenTruthy('x')->toArray();

    expect($node['whenTruthy'])->toBe(['x']);
});

it('omits whenTruthy and whenFalsy when not set', function () {
    $node = TextInput::make('n')->toArray();

    expect($node)->not->toHaveKey('whenTruthy');
    expect($node)->not->toHaveKey('whenFalsy');
});

it('reports whenTruthy and whenFalsy', function () {
    $field = TextInput::make('n')->whenTruthy('a')->whenFalsy('b');

    expect($field->getWhenTruthy())->toBe(['a']);
    expect($field->getWhenFalsy())->toBe(['b']);
    expect(TextInput::make('n')->getWhenTruthy())->toBeNull();
    expect(TextInput::make('n')->getWhenFalsy())->toBeNull();
});
