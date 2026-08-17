<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\ToggleColumn;
use Workbench\App\Models\Comment;

it('serializes a truthy boolean state as a toggle value', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    expect(ToggleColumn::make('is_visible')->serializeCell($comment))->toBe(['value' => true]);
});

it('serializes a falsy boolean state as a toggle value', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);

    expect(ToggleColumn::make('is_visible')->serializeCell($comment))->toBe(['value' => false]);
});

it('is editable by default and serializes the editable flag', function () {
    $column = ToggleColumn::make('is_visible');

    expect($column->isEditable())->toBeTrue();
    expect($column->toArray()['editable'])->toBeTrue();
    expect($column->toArray()['kind'])->toBe('toggle');
});

it('serializes the on colour and on/off icons', function () {
    $column = ToggleColumn::make('is_visible')
        ->onColor('success')
        ->onIcon('heroicon-m-check')
        ->offIcon('heroicon-m-x-mark');

    $array = $column->toArray();

    expect($array['onColor'])->toBe('success');
    expect($array['onIcon'])->toBe('heroicon-m-check');
    expect($array['offIcon'])->toBe('heroicon-m-x-mark');
});

it('omits the colour and icon keys when not set', function () {
    $array = ToggleColumn::make('is_visible')->toArray();

    expect($array)->not->toHaveKey('onColor');
    expect($array)->not->toHaveKey('onIcon');
    expect($array)->not->toHaveKey('offIcon');
});
