<?php

declare(strict_types=1);

use Refilament\Refilament\Infolists\Components\ViewEntry;

it('returns the view_entry type', function () {
    expect(ViewEntry::make('stats-card')->getType())->toBe('view_entry');
});

it('make() sets the view key', function () {
    expect(ViewEntry::make('stats-card')->getView())->toBe('stats-card');
});

it('serializes the view and viewData', function () {
    $node = ViewEntry::make('stats-card')->viewData(['total' => 42])->toArray();

    expect($node['type'])->toBe('view_entry');
    expect($node['view'])->toBe('stats-card');
    expect($node['viewData'])->toBe(['total' => 42]);
});

it('omits viewData when empty', function () {
    $node = ViewEntry::make('stats-card')->toArray();

    expect($node['view'])->toBe('stats-card');
    expect($node)->not->toHaveKey('viewData');
});

it('omits a missing view key', function () {
    $node = ViewEntry::make()->toArray();

    expect($node['type'])->toBe('view_entry');
    expect($node)->not->toHaveKey('view');
    expect($node)->not->toHaveKey('viewData');
});
