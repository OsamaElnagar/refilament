<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\ColorColumn;
use Workbench\App\Models\Comment;

it('serializes a color state to a swatch array', function () {
    $comment = Comment::factory()->make(['color' => '#10b981']);

    expect(ColorColumn::make('color')->serializeCell($comment))->toBe([
        'value' => '#10b981',
        'colors' => ['#10b981'],
    ]);
});

it('serializes an array of color states', function () {
    $comment = Comment::factory()->make(['color' => ['#10b981', '#f59e0b']]);

    expect(ColorColumn::make('color')->serializeCell($comment)['colors'])->toBe(['#10b981', '#f59e0b']);
});

it('renders an empty placeholder for a null state', function () {
    $comment = Comment::factory()->make(['color' => null]);

    expect(ColorColumn::make('color')->serializeCell($comment))->toBeNull();
});

it('serializes the copyable flag on the definition', function () {
    $payload = ColorColumn::make('color')->copyable()->toArray();

    expect($payload['kind'])->toBe('color');
    expect($payload['copyable'])->toBeTrue();
});
