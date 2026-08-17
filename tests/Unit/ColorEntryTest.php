<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\ColorEntry;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

beforeEach(function () {
    $this->post = Post::factory()->create([
        'title' => 'Hello World',
        'views' => 1234,
        'status' => 'published',
    ]);
});

it('serializes a color entry with a static color and record value', function () {
    $schema = Schema::make()->components([
        ColorEntry::make('status')->label('Status')->color('success'),
    ])->record($this->post);

    $node = $schema->toArray()['schema'][0];

    expect($node)->toBe([
        'type' => 'color_entry',
        'name' => 'status',
        'label' => 'Status',
        'value' => 'published',
        'color' => 'success',
    ]);
});

it('serializes null value when no record is bound', function () {
    $node = ColorEntry::make('missing')->label('Missing')->toArray();

    expect($node['value'])->toBeNull();
    expect($node)->not->toHaveKey('color');
});
it('serializes copyable with a copyable state override', function () {
    $node = Schema::make()->components([
        ColorEntry::make('accent')
            ->getStateUsing(fn () => '#6366f1')
            ->copyable()
            ->copyableState('refilament-indigo'),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'])->toBe('#6366f1');
    expect($node['copyable'])->toBeTrue();
    expect($node['copyableState'])->toBe('refilament-indigo');
    expect($node)->not->toHaveKey('copyMessage');
});

it('omits copy keys unless copyable', function () {
    $node = Schema::make()->components([
        ColorEntry::make('accent')->getStateUsing(fn () => '#22c55e'),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['value'])->toBe('#22c55e');
    expect($node)->not->toHaveKey('copyable');
    expect($node)->not->toHaveKey('copyableState');
    expect($node)->not->toHaveKey('copyMessage');
});

it('serializes a custom copy message', function () {
    $node = Schema::make()->components([
        ColorEntry::make('accent')->copyable()->copyMessage('Color copied'),
    ])->record($this->post)->toArray()['schema'][0];

    expect($node['copyable'])->toBeTrue();
    expect($node['copyMessage'])->toBe('Color copied');
});
