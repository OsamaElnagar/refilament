<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;

it('registers resource tables and forms from a directory', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResourcesFromDirectory(
        __DIR__.'/../Fixtures',
        'Refilament\\Refilament\\Tests\\Fixtures',
    );

    $table = $refilament->resolveTable('demo');
    expect($table)->not->toBeNull();
    expect($table?->getColumns())->toHaveCount(2);
    expect($table?->getId())->toBe('demo');

    $schema = $refilament->resolveSchema('demo-form');
    expect($schema)->not->toBeNull();
    expect($schema?->getComponents())->toHaveCount(2);
    expect($schema?->getId())->toBe('demo-form');
});

it('skips resources that opt out of discovery', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResourcesFromDirectory(
        __DIR__.'/../Fixtures',
        'Refilament\\Refilament\\Tests\\Fixtures',
    );

    expect($refilament->resolveTable('hidden'))->toBeNull();
    expect($refilament->resolveSchema('hidden-form'))->toBeNull();
});

it('is a no-op for a missing directory', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResourcesFromDirectory(sys_get_temp_dir().'/refilament-missing', 'App\\Nothing');

    expect($refilament->resolveTable('anything'))->toBeNull();
});
