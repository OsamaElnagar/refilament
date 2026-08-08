<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\Select;

it('serializes a plain select with static options', function () {
    $node = Select::make('status')
        ->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])
        ->toArray();

    expect($node)->toBe([
        'type' => 'select',
        'name' => 'status',
        'label' => 'Status',
        'options' => [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ],
    ]);
});

it('serializes a searchable select', function () {
    $node = Select::make('country')->searchable()->options(['us' => 'United States'])->toArray();

    expect($node['searchable'])->toBeTrue();
});

it('serializes a multiple select', function () {
    $node = Select::make('tags')->multiple()->options(['a' => 'A', 'b' => 'B'])->toArray();

    expect($node['multiple'])->toBeTrue();
});

it('omits the flags when not set', function () {
    $node = Select::make('status')->toArray();

    expect($node)->not->toHaveKey('multiple');
    expect($node)->not->toHaveKey('searchable');
});

it('inherits placeholder from the base component', function () {
    $node = Select::make('status')->placeholder('Pick one')->toArray();

    expect($node['placeholder'])->toBe('Pick one');
});

it('serializes dependsOn', function () {
    $node = Select::make('state')->dependsOn(['country'])->toArray();

    expect($node['dependsOn'])->toBe(['country']);
});

it('omits static options when an options resolver is registered', function () {
    $node = Select::make('state')
        ->options(['x' => 'Static'])
        ->dependsOn(['country'])
        ->resolveOptionsUsing(fn (array $data): array => [])
        ->toArray();

    expect($node)->toHaveKey('dependsOn');
    expect($node)->not->toHaveKey('options');
});

it('never serializes the options resolver closure', function () {
    $node = Select::make('state')
        ->dependsOn(['country'])
        ->resolveOptionsUsing(fn (array $data): array => ['x' => 'X'])
        ->toArray();

    expect($node)->not->toHaveKey('optionsResolver');
});

it('requires dependsOn when an options resolver is registered', function () {
    Select::make('state')
        ->resolveOptionsUsing(fn (array $data): array => [])
        ->toArray();
})->throws(LogicException::class, 'must declare [dependsOn()]');

it('resolves options to the contract shape', function () {
    $field = Select::make('state')
        ->dependsOn(['country'])
        ->resolveOptionsUsing(fn (array $data): array => $data['country'] === 'us'
            ? ['al' => 'Alabama', 'ca' => 'California']
            : []);

    expect($field->resolveOptions(['country' => 'us']))->toBe([
        ['value' => 'al', 'label' => 'Alabama'],
        ['value' => 'ca', 'label' => 'California'],
    ]);

    expect($field->resolveOptions(['country' => 'gb']))->toBe([]);
});

it('reports whether an options resolver is registered', function () {
    $plain = Select::make('state');
    $resolved = Select::make('state')->resolveOptionsUsing(fn (array $data): array => []);

    expect($plain->hasOptionsResolver())->toBeFalse();
    expect($plain->resolveOptions([]))->toBe([]);
    expect($resolved->hasOptionsResolver())->toBeTrue();
});
