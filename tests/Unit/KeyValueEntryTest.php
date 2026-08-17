<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\KeyValueEntry;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

beforeEach(function () {
    $this->post = Post::factory()->create();
});

it('returns the key_value_entry type', function () {
    expect(KeyValueEntry::make('meta')->getType())->toBe('key_value_entry');
});

it('uses default labels', function () {
    expect(KeyValueEntry::make('meta')->getKeyLabel())->toBe('Key');
    expect(KeyValueEntry::make('meta')->getValueLabel())->toBe('Value');
});

it('serializes the key and value labels', function () {
    $node = Schema::make()->components([
        KeyValueEntry::make('meta')->keyLabel('Setting')->valueLabel('Value'),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['keyLabel'])->toBe('Setting');
    expect($node['valueLabel'])->toBe('Value');
});

it('normalizes an associative map to rows', function () {
    $node = Schema::make()->components([
        KeyValueEntry::make('meta')->getStateUsing(fn () => ['theme' => 'dark', 'language' => 'en']),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'])->toBe([
        ['key' => 'theme', 'value' => 'dark'],
        ['key' => 'language', 'value' => 'en'],
    ]);
});

it('passes through list rows unchanged', function () {
    $node = Schema::make()->components([
        KeyValueEntry::make('meta')->getStateUsing(fn () => [
            ['key' => 'a', 'value' => '1'],
            ['key' => 'b', 'value' => '2'],
        ]),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'])->toBe([
        ['key' => 'a', 'value' => '1'],
        ['key' => 'b', 'value' => '2'],
    ]);
});

it('scalarizes structured values', function () {
    $node = Schema::make()->components([
        KeyValueEntry::make('meta')->getStateUsing(fn () => ['nested' => ['a' => 1]]),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'][0]['value'])->toBeString();
});

it('ships an empty row list when there is no value', function () {
    $node = KeyValueEntry::make('meta')->toArray();

    expect($node['value'])->toBe([]);
});
