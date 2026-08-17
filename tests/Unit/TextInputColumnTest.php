<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\TextInputColumn;
use Workbench\App\Models\Post;

it('is editable by default and serializes the editable flag', function () {
    $column = TextInputColumn::make('title');

    expect($column->isEditable())->toBeTrue();
    expect($column->toArray()['editable'])->toBeTrue();
    expect($column->toArray()['kind'])->toBe('text');
});

it('ships the raw value as the cell state', function () {
    $post = Post::factory()->create(['title' => 'Hello']);

    expect(TextInputColumn::make('title')->serializeCell($post))->toBe('Hello');
});

it('serializes a custom type', function () {
    $column = TextInputColumn::make('views')->type('email');

    expect($column->toArray()['type'])->toBe('email');
});

it('omits the type key when not set', function () {
    expect(TextInputColumn::make('views')->toArray())->not->toHaveKey('type');
});

it('serializes a numeric input via type() and inputMode()', function () {
    $array = TextInputColumn::make('views')->type('number')->inputMode('decimal')->toArray();

    expect($array['type'])->toBe('number');
    expect($array['inputMode'])->toBe('decimal');
});

it('serializes a numeric step', function () {
    $array = TextInputColumn::make('views')->type('number')->step(0.01)->toArray();

    expect($array['type'])->toBe('number');
    expect($array['step'])->toBe(0.01);
});

it('omits the step key when not set', function () {
    expect(TextInputColumn::make('views')->toArray())->not->toHaveKey('step');
});

it('maxLength() serializes the attribute and appends a max rule', function () {
    $column = TextInputColumn::make('title')->maxLength(50);

    expect($column->toArray()['maxLength'])->toBe(50);
    expect($column->getEditRules())->toContain('max:50');
});
