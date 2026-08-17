<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\CodeEntry;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

beforeEach(function () {
    $this->post = Post::factory()->create();
});

it('returns the code_entry type', function () {
    expect(CodeEntry::make('code')->getType())->toBe('code_entry');
});

it('omits the option keys by default', function () {
    $node = CodeEntry::make('code')->toArray();

    expect($node['value'])->toBeNull();
    expect($node)->not->toHaveKey('language');
    expect($node)->not->toHaveKey('lineNumbers');
    expect($node)->not->toHaveKey('copyable');
});

it('serializes language, line numbers and copyable', function () {
    $node = Schema::make()->components([
        CodeEntry::make('snippet')
            ->getStateUsing(fn () => '<h1>Hi</h1>')
            ->language('html')
            ->lineNumbers()
            ->copyable(),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'])->toBe('<h1>Hi</h1>');
    expect($node['language'])->toBe('html');
    expect($node['lineNumbers'])->toBeTrue();
    expect($node['copyable'])->toBeTrue();
});

it('pretty-prints array values as json', function () {
    $node = Schema::make()->components([
        CodeEntry::make('config')->getStateUsing(fn () => ['key' => 'value']),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'])->toBe(json_encode(['key' => 'value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
});
