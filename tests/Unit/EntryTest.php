<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\Entry;
use Refilament\Refilament\Infolists\Components\TextEntry;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

beforeEach(function () {
    $this->post = Post::factory()->create([
        'title' => 'Hello World',
        'views' => 1234,
        'status' => 'published',
    ]);
});

it('serializes a plain text entry from the bound record', function () {
    $schema = Schema::make()->components([
        TextEntry::make('title')->label('Title'),
    ])->record($this->post);

    $node = $schema->toArray()['schema'][0];

    expect($node)->toBe([
        'type' => 'text_entry',
        'name' => 'title',
        'label' => 'Title',
        'value' => 'Hello World',
    ]);
});

it('formats numeric values server-side', function () {
    $schema = Schema::make()->components([
        TextEntry::make('views')->label('Views')->numeric(),
    ])->record($this->post);

    expect($schema->toArray()['schema'][0]['value'])->toBe(number_format(1234));
});

it('resolves dot-notation relationship entries', function () {
    $schema = Schema::make()->components([
        TextEntry::make('user.name')->label('Author'),
    ])->record($this->post);

    expect($schema->toArray()['schema'][0]['value'])->toBe($this->post->user->name);
});

it('serializes badge and color flags', function () {
    $schema = Schema::make()->components([
        TextEntry::make('status')
            ->label('Status')
            ->badge()
            ->color(fn (string $state): string => $state === 'published' ? 'success' : 'secondary'),
    ])->record($this->post);

    $node = $schema->toArray()['schema'][0];

    expect($node['badge'])->toBeTrue();
    expect($node['color'])->toBe('success');
});

it('sends null value with a placeholder when the record is absent', function () {
    $node = TextEntry::make('missing')->label('Missing')->placeholder('—')->toArray();

    expect($node['value'])->toBeNull();
    expect($node['placeholder'])->toBe('—');
});

it('does not serialize an unresolved record as an empty value', function () {
    // No ->record() bound: the entry value stays null rather than resolving.
    $schema = Schema::make()->components([
        TextEntry::make('title')->label('Title'),
    ]);

    expect($schema->toArray()['schema'][0]['value'])->toBeNull();
});
