<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Action;

it('serializes an openUrlInNewTab flag alongside the url', function () {
    $payload = Action::make('view')
        ->url('/refilament/users')
        ->openUrlInNewTab()
        ->toArray();

    expect($payload)->toBe([
        'name' => 'view',
        'label' => 'View',
        'url' => '/refilament/users',
        'openUrlInNewTab' => true,
    ]);
});

it('omits openUrlInNewTab when the url opens in place', function () {
    $payload = Action::make('view')->url('/refilament/users')->toArray();

    expect($payload)->not->toHaveKey('openUrlInNewTab');
});

it('serializes the visibleWhenFilled client-side visibility rule', function () {
    $payload = Action::make('view')
        ->visibleWhenFilled(['author', 'title'])
        ->toArray();

    expect($payload['visibleWhenFilled'])->toBe(['author', 'title']);
});

it('omits visibleWhenFilled when no fields are declared', function () {
    expect(Action::make('view')->toArray())->not->toHaveKey('visibleWhenFilled');
});
