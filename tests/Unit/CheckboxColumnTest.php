<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\CheckboxColumn;
use Workbench\App\Models\Comment;

it('serializes a truthy boolean state as a checkbox value', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    expect(CheckboxColumn::make('is_visible')->serializeCell($comment))->toBe(['value' => true]);
});

it('serializes a falsy boolean state as a checkbox value', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);

    expect(CheckboxColumn::make('is_visible')->serializeCell($comment))->toBe(['value' => false]);
});

it('is editable by default and serializes the editable flag', function () {
    $column = CheckboxColumn::make('is_visible');

    expect($column->isEditable())->toBeTrue();
    expect($column->toArray()['editable'])->toBeTrue();
    expect($column->toArray()['kind'])->toBe('checkbox');
});

it('serializes the on and off colours', function () {
    $column = CheckboxColumn::make('is_visible')->onColor('success')->offColor('danger');

    expect($column->toArray()['onColor'])->toBe('success');
    expect($column->toArray()['offColor'])->toBe('danger');
});

it('omits the colour keys when not set', function () {
    $array = CheckboxColumn::make('is_visible')->toArray();

    expect($array)->not->toHaveKey('onColor');
    expect($array)->not->toHaveKey('offColor');
});
