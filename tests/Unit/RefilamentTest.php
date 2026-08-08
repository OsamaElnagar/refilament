<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;

it('registers and resolves schema resolvers by key', function () {
    $refilament = app(Refilament::class);

    $refilament->registerSchemaResolver('playground', fn (): Schema => Schema::make()->id('playground'));

    $schema = $refilament->resolveSchema('playground');

    expect($schema)->toBeInstanceOf(Schema::class);
    expect($schema?->getId())->toBe('playground');
});

it('returns null for unregistered schemas', function () {
    $schema = app(Refilament::class)->resolveSchema('missing');

    expect($schema)->toBeNull();
});

it('serializes the schema id into the envelope', function () {
    $document = Schema::make()->id('playground')->components([])->toArray();

    expect($document)->toBe([
        'id' => 'playground',
        'contract' => Schema::CONTRACT_VERSION,
        'schema' => [],
    ]);
});

it('omits the id when not set', function () {
    $document = Schema::make()->toArray();

    expect($document)->not->toHaveKey('id');
});
