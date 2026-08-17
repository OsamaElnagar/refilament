<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\TagsInput;

it('serializes a plain tags input node', function () {
    expect(TagsInput::make('tags')->toArray())->toBe([
        'type' => 'tags_input',
        'name' => 'tags',
        'label' => 'Tags',
        'separator' => ',',
    ]);
});

it('serializes reorderable and separator', function () {
    $node = TagsInput::make('tags')->reorderable()->separator(';')->toArray();

    expect($node['reorderable'])->toBe(true);
    expect($node['separator'])->toBe(';');
});

it('defaults the separator to a comma', function () {
    expect(TagsInput::make('tags')->getSeparator())->toBe(',');
});

it('serializes split keys and suggestions', function () {
    $node = TagsInput::make('tags')
        ->splitKeys(['Enter', 'Tab', ','])
        ->suggestions(['laravel', 'inertia', 'react'])
        ->toArray();

    expect($node['splitKeys'])->toBe(['Enter', 'Tab', ',']);
    expect($node['suggestions'])->toBe(['laravel', 'inertia', 'react']);
});

it('serializes tag prefix and suffix', function () {
    $node = TagsInput::make('tags')->tagPrefix('#')->tagSuffix('!')->toArray();

    expect($node['tagPrefix'])->toBe('#');
    expect($node['tagSuffix'])->toBe('!');
});

it('omits optional keys when not configured', function () {
    $node = TagsInput::make('tags')->toArray();

    expect($node)->not->toHaveKey('reorderable');
    expect($node)->not->toHaveKey('splitKeys');
    expect($node)->not->toHaveKey('suggestions');
    expect($node)->not->toHaveKey('tagPrefix');
    expect($node)->not->toHaveKey('tagSuffix');
    expect($node['separator'])->toBe(',');
});
