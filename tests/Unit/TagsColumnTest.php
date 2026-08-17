<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Columns\TagsColumn;
use Workbench\App\Models\Comment;

it('serializes an array state to a badge list', function () {
    $comment = Comment::factory()->make(['tags' => ['laravel', 'inertia', 'react']]);

    expect(TagsColumn::make('tags')->serializeCell($comment))->toBe([
        'value' => 'laravel, inertia, react',
        'tags' => ['laravel', 'inertia', 'react'],
    ]);
});

it('wraps a single string state into one badge', function () {
    $comment = Comment::factory()->make(['tags' => 'featured']);

    expect(TagsColumn::make('tags')->serializeCell($comment))->toBe([
        'value' => 'featured',
        'tags' => ['featured'],
    ]);
});

it('drops empty entries from the tag list', function () {
    $comment = Comment::factory()->make(['tags' => ['a', '', null, 'b']]);

    expect(TagsColumn::make('tags')->serializeCell($comment)['tags'])->toBe(['a', 'b']);
});

it('caps the badge list with a remaining count', function () {
    $comment = Comment::factory()->make(['tags' => ['a', 'b', 'c', 'd', 'e']]);

    $cell = TagsColumn::make('tags')->limitList(2)->serializeCell($comment);

    expect($cell['tags'])->toBe(['a', 'b']);
    expect($cell['remaining'])->toBe(3);
});

it('renders an empty placeholder for a null state', function () {
    $comment = Comment::factory()->make(['tags' => null]);

    expect(TagsColumn::make('tags')->serializeCell($comment))->toBeNull();
});

it('serializes the tags column definition with kind and limit', function () {
    $payload = TagsColumn::make('tags')->limitList(3)->toArray();

    expect($payload['kind'])->toBe('tags');
    expect($payload['limit'])->toBe(3);
});
