<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;
use Workbench\App\Models\Post;

it('creates a record through the submit endpoint', function () {
    $response = $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [
            'title' => 'Hello world',
            'slug' => 'hello-world',
            'status' => 'published',
            'author' => 'Ada Lovelace',
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', 'Post created.');

    $this->assertDatabaseHas('posts', [
        'title' => 'Hello world',
        'slug' => 'hello-world',
        'status' => 'published',
        'author' => 'Ada Lovelace',
    ]);
});

it('maps 422 errors back onto the fields by name with labels in messages', function () {
    $response = $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.title.0', 'The Title field is required.');
    $response->assertJsonPath('errors.slug.0', 'The Slug field is required.');
    $response->assertJsonPath('errors.status.0', 'The Status field is required.');
    $response->assertJsonPath('errors.author.0', 'The Author field is required.');
});

it('rejects a status outside the select options', function () {
    $response = $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [
            'title' => 'Valid title',
            'slug' => 'valid-slug',
            'status' => 'nope',
            'author' => 'Ada Lovelace',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.status.0', 'The selected Status is invalid.');
});

it('rejects a duplicate slug via the unique rule', function () {
    Post::factory()->create(['slug' => 'taken']);

    $response = $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [
            'title' => 'Another post',
            'slug' => 'taken',
            'status' => 'draft',
            'author' => 'Ada Lovelace',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.slug.0', 'The Slug has already been taken.');
    $this->assertDatabaseCount('posts', 1);
});

it('rejects an unknown schema', function () {
    $this->postJson('/refilament/schema/missing/submit', [
        'data' => [],
    ])->assertNotFound();
});

it('surfaces a missing submit handler as a 500', function () {
    $refilament = app(Refilament::class);

    $refilament->registerSchemaResolver('no-handler', fn (): Schema => Schema::make()->id('no-handler'));

    $this->postJson('/refilament/schema/no-handler/submit', [
        'data' => [],
    ])->assertStatus(500);
});

it('maps a domain exception in the submit handler to a 422', function () {
    $refilament = app(Refilament::class);

    $refilament->registerSchemaResolver('failing', fn (): Schema => Schema::make()
        ->id('failing')
        ->submitUsing(static function (array $data): void {
            throw new RuntimeException('Something went wrong.');
        }));

    $response = $this->postJson('/refilament/schema/failing/submit', [
        'data' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.form.0', 'Something went wrong.');
});

it('validates the request payload', function () {
    $this->postJson('/refilament/schema/post-form/submit', [])->assertStatus(422);
});
