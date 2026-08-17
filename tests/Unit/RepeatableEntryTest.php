<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\RepeatableEntry;
use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

it('returns the repeatable type', function () {
    expect(RepeatableEntry::make('items')->getType())->toBe('repeatable');
});

it('serializes each item against its own array data', function () {
    $post = Post::factory()->create();

    $node = Schema::make()->components([
        RepeatableEntry::make('items')
            ->getStateUsing(fn () => [
                ['word' => 'Hello', 'length' => 5],
                ['word' => 'World', 'length' => 5],
            ])
            ->schema([
                TextEntry::make('word')->label('Word'),
                TextEntry::make('length')->label('Length')->numeric(),
            ]),
    ])->record($post)->toArray()['schema'][0];

    expect($node['items'])->toBe([
        [
            ['type' => 'text_entry', 'name' => 'word', 'label' => 'Word', 'value' => 'Hello'],
            ['type' => 'text_entry', 'name' => 'length', 'label' => 'Length', 'value' => '5'],
        ],
        [
            ['type' => 'text_entry', 'name' => 'word', 'label' => 'Word', 'value' => 'World'],
            ['type' => 'text_entry', 'name' => 'length', 'label' => 'Length', 'value' => '5'],
        ],
    ]);
});

it('resolves child entries against a model item', function () {
    $post = Post::factory()->create(['title' => 'Nested post']);

    $node = Schema::make()->components([
        RepeatableEntry::make('items')
            ->getStateUsing(fn (Post $record): array => [$record])
            ->schema([
                TextEntry::make('title')->label('Title'),
            ]),
    ])->record($post)->toArray()['schema'][0];

    expect($node['items'][0][0]['value'])->toBe('Nested post');
});

it('ships an empty items list when there is no state', function () {
    $node = RepeatableEntry::make('items')->schema([TextEntry::make('word')])->toArray();

    expect($node['items'])->toBe([]);
});

it('serializes a placeholder', function () {
    $node = RepeatableEntry::make('items')->placeholder('None')->toArray();

    expect($node['placeholder'])->toBe('None');
});

it('omits the placeholder key when not set', function () {
    expect(RepeatableEntry::make('items')->toArray())->not->toHaveKey('placeholder');
});
