<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tests\Fixtures\AutoCreateResource;
use Workbench\App\Models\Post;

beforeEach(function () {
    // AutoCreateResource declares a Post model but NO submitUsing() handler —
    // the schema its form registers under must fall back to the model create
    // default (slice 2.6, docs/ROADMAP.md "2.6 Default create").
    app(Refilament::class)->registerResources(AutoCreateResource::class);
});

it('auto-creates through the resource model when the form declares no submitUsing()', function () {
    $response = $this->postJson('/refilament/schema/auto-create-form/submit', [
        'data' => [
            'title' => 'Auto-created post',
            'slug' => 'auto-created-post',
            'author' => 'Ada Lovelace',
            'status' => 'draft',
        ],
    ]);

    $response->assertOk();
    // The default create path ships a sensible success message too.
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', 'Created successfully.');

    $this->assertDatabaseHas('posts', [
        'title' => 'Auto-created post',
        'slug' => 'auto-created-post',
        'status' => 'draft',
    ]);
});

it('does not leak the model class onto the serialized document', function () {
    $this->getJson('/refilament/schema/auto-create-form')
        ->assertOk()
        ->assertJsonMissingPath('model');
});

it('still throws for a resource-less schema with no handler', function () {
    app(Refilament::class)->registerSchemaResolver('standalone', fn (): Schema => Schema::make()->id('standalone'));

    $this->postJson('/refilament/schema/standalone/submit', [
        'data' => [],
    ])->assertStatus(500);
});

it('lets an explicit submitUsing() handler win over the model default', function () {
    // The workbench user form declares its own handler — the model default
    // must never run for it.
    $this->postJson('/refilament/schema/user-form/submit', [
        'data' => [
            'name' => 'Defaulted',
            'email' => 'defaulted@example.com',
            'password' => 'secret123',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User created.');
});

it('wires the model onto the resolved schema so submit() can default', function () {
    $schema = app(Refilament::class)->resolveSchema('auto-create-form');

    expect($schema)->not->toBeNull();
    expect($schema->getModel())->toBe(Post::class);
});
