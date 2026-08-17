<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\BooleanColumn;
use Refilament\Refilament\Tables\Columns\IconColumn;
use Workbench\App\Models\Comment;

it('serializes a truthy boolean state with the default icon and colour', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    expect(IconColumn::make('is_visible')->boolean()->serializeCell($comment))->toBe([
        'value' => 'Yes',
        'icon' => 'check-circle',
        'iconColor' => 'success',
    ]);
});

it('serializes a falsy boolean state with the default icon and colour', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);

    expect(IconColumn::make('is_visible')->boolean()->serializeCell($comment))->toBe([
        'value' => 'No',
        'icon' => 'x-circle',
        'iconColor' => 'danger',
    ]);
});

it('renders an empty placeholder for a null boolean state', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);
    $comment->is_visible = null; // in-memory only — the column is NOT NULL

    expect(IconColumn::make('is_visible')->boolean()->serializeCell($comment))->toBeNull();
});

it('overrides the true and false icons and colours', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    $column = IconColumn::make('is_visible')
        ->boolean()
        ->true('heroicon-m-check', 'primary')
        ->false('heroicon-m-x-mark', 'warning');

    expect($column->serializeCell($comment))->toBe([
        'value' => 'Yes',
        'icon' => 'heroicon-m-check',
        'iconColor' => 'primary',
    ]);

    $comment->update(['is_visible' => false]);

    expect($column->serializeCell($comment))->toBe([
        'value' => 'No',
        'icon' => 'heroicon-m-x-mark',
        'iconColor' => 'warning',
    ]);
});

it('enables boolean mode from a single state configurer', function () {
    $column = IconColumn::make('is_visible')->trueColor('success');

    expect($column->isBoolean())->toBeTrue();
});

it('serializes a plain icon column with a per-record icon and colour', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    $cell = IconColumn::make('is_visible')
        ->icon(fn (Comment $record): string => $record->is_visible ? 'eye' : 'eye-slash')
        ->iconColor(fn (Comment $record): string => $record->is_visible ? 'success' : 'danger')
        ->serializeCell($comment);

    expect($cell)->toBe([
        'value' => '1',
        'icon' => 'eye',
        'iconColor' => 'success',
    ]);
});

it('falls back to the raw value when a plain icon column has no icon', function () {
    $comment = Comment::factory()->create(['is_visible' => false]);

    // The model casts the boolean, so the raw state is `false` — stringified
    // as '' by the plain-column fallback.
    expect(IconColumn::make('is_visible')->serializeCell($comment))->toBe('');
});

it('deprecated BooleanColumn always serializes the boolean shape', function () {
    $comment = Comment::factory()->create(['is_visible' => true]);

    expect(BooleanColumn::make('is_visible')->serializeCell($comment))->toBe([
        'value' => 'Yes',
        'icon' => 'check-circle',
        'iconColor' => 'success',
    ]);
});
