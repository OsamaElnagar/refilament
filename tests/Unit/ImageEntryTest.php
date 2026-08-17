<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\ImageEntry;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

beforeEach(function () {
    $this->post = Post::factory()->create();
});

it('returns the image_entry type', function () {
    expect(ImageEntry::make('photo')->getType())->toBe('image_entry');
});

it('normalizes a single url string to a list', function () {
    $node = Schema::make()->components([
        ImageEntry::make('photo')->getStateUsing(fn () => 'https://x.dev/a.png'),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['images'])->toBe(['https://x.dev/a.png']);
});

it('serializes the display options', function () {
    $node = Schema::make()->components([
        ImageEntry::make('gallery')
            ->getStateUsing(fn () => ['a', 'b', 'c'])
            ->size(48)
            ->square()
            ->stacked()
            ->ring(4)
            ->limit(2),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['images'])->toBe(['a', 'b', 'c']);
    expect($node['size'])->toBe(48);
    expect($node['square'])->toBeTrue();
    expect($node['stacked'])->toBeTrue();
    expect($node['ring'])->toBe(4);
    expect($node['limit'])->toBe(2);
});

it('applies a circular crop', function () {
    $node = Schema::make()->components([
        ImageEntry::make('photo')->getStateUsing(fn () => 'https://x.dev/a.png')->circular(),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['circular'])->toBeTrue();
    expect($node)->not->toHaveKey('square');
});

it('ships empty images for a null value without a record', function () {
    $node = ImageEntry::make('photo')->toArray();

    expect($node['images'])->toBe([]);
    expect($node['value'])->toBeNull();
});

it('omits optional display keys when unset', function () {
    $node = Schema::make()->components([
        ImageEntry::make('photo')->getStateUsing(fn () => 'https://x.dev/a.png'),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['images'])->toBe(['https://x.dev/a.png']);
    expect($node)->not->toHaveKey('size');
    expect($node)->not->toHaveKey('circular');
    expect($node)->not->toHaveKey('square');
    expect($node)->not->toHaveKey('stacked');
    expect($node)->not->toHaveKey('ring');
    expect($node)->not->toHaveKey('limit');
});
