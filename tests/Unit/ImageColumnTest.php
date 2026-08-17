<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\ImageColumn;
use Workbench\App\Models\Comment;

it('serializes an array state to image URLs', function () {
    $comment = Comment::factory()->make(['avatar' => ['https://example.com/a.jpg', 'https://example.com/b.jpg']]);

    expect(ImageColumn::make('avatar')->serializeCell($comment))->toBe([
        'value' => 'https://example.com/a.jpg, https://example.com/b.jpg',
        'images' => ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
    ]);
});

it('wraps a single URL state into one image', function () {
    $comment = Comment::factory()->make(['avatar' => 'https://example.com/a.jpg']);

    expect(ImageColumn::make('avatar')->serializeCell($comment)['images'])->toBe(['https://example.com/a.jpg']);
});

it('caps the images with a remaining count when limited', function () {
    $comment = Comment::factory()->make(['avatar' => ['a.jpg', 'b.jpg', 'c.jpg']]);

    $cell = ImageColumn::make('avatar')->limit(2)->limitedRemainingText()->serializeCell($comment);

    expect($cell['images'])->toBe(['a.jpg', 'b.jpg']);
    expect($cell['remaining'])->toBe(1);
});

it('renders the default image when the state is blank', function () {
    $comment = Comment::factory()->make(['avatar' => null]);

    expect(ImageColumn::make('avatar')->defaultImageUrl('https://example.com/fallback.jpg')->serializeCell($comment))
        ->toBe(['value' => 'https://example.com/fallback.jpg', 'images' => ['https://example.com/fallback.jpg']]);
});

it('renders an empty placeholder for a blank state without a default', function () {
    $comment = Comment::factory()->make(['avatar' => null]);

    expect(ImageColumn::make('avatar')->serializeCell($comment))->toBeNull();
});

it('serializes the image column definition with its presentation options', function () {
    $payload = ImageColumn::make('avatar')
        ->imageSize(48)
        ->circular()
        ->stacked()
        ->ring(2)
        ->overlap(3)
        ->limit(3)
        ->limitedRemainingText()
        ->toArray();

    expect($payload['kind'])->toBe('image');
    expect($payload['size'])->toBe(48);
    expect($payload['circular'])->toBeTrue();
    expect($payload['stacked'])->toBeTrue();
    expect($payload['ring'])->toBe(2);
    expect($payload['overlap'])->toBe(3);
    expect($payload['limit'])->toBe(3);
    expect($payload['limitedRemainingText'])->toBeTrue();
});
