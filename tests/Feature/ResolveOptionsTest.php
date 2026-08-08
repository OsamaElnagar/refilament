<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Schema;

it('resolves fresh options for a dependent field', function () {
    $response = $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'playground',
        'field' => 'state',
        'data' => ['country' => 'us'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('options', [
        ['value' => 'al', 'label' => 'Alabama'],
        ['value' => 'ca', 'label' => 'California'],
        ['value' => 'ny', 'label' => 'New York'],
    ]);
});

it('resolves different options per dependency value', function () {
    $response = $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'playground',
        'field' => 'state',
        'data' => ['country' => 'gb'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('options.0', ['value' => 'eng', 'label' => 'England']);
});

it('returns empty options when no dependency is selected', function () {
    $response = $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'playground',
        'field' => 'state',
        'data' => ['country' => ''],
    ]);

    $response->assertOk();
    $response->assertJsonPath('options', []);
});

it('rejects an unknown schema', function () {
    $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'missing',
        'field' => 'state',
        'data' => [],
    ])->assertNotFound();
});

it('rejects an unknown field', function () {
    $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'playground',
        'field' => 'missing',
        'data' => [],
    ])->assertNotFound();
});

it('rejects a field without an options resolver', function () {
    $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'playground',
        'field' => 'status',
        'data' => [],
    ])->assertStatus(422);
});

it('rejects a field with a resolver but no dependsOn', function () {
    $refilament = app(Refilament::class);

    $refilament->registerSchemaResolver('broken', fn (): Schema => Schema::make()
        ->id('broken')
        ->components([
            Select::make('lonely')->resolveOptionsUsing(fn (array $data): array => ['a' => 'A']),
        ]));

    $this->postJson('/refilament/schema/resolve-options', [
        'schema' => 'broken',
        'field' => 'lonely',
        'data' => [],
    ])->assertStatus(422);
});

it('validates the request payload', function () {
    $this->postJson('/refilament/schema/resolve-options', [
        'field' => 'state',
    ])->assertStatus(422);
});
