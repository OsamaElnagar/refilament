<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Action;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Resources\UserResource;
use Workbench\App\Refilament\Tables\PostsTable;

it('serializes the modal create header action on the posts table', function () {
    $payload = PostsTable::configure(Table::make())->toArray();

    expect($payload['headerActions'])->toHaveCount(1);
    expect($payload['headerActions'][0])->toBe([
        'name' => 'create',
        'label' => 'New Post',
        'type' => 'create',
        'schema' => 'post-form',
    ]);
});

it('serializes the modal create header action on the users table', function () {
    $payload = UserResource::table(Table::make())->toArray();

    expect($payload['headerActions'])->toHaveCount(1);
    expect($payload['headerActions'][0])->toBe([
        'name' => 'create',
        'label' => 'New User',
        'type' => 'create',
        'schema' => 'user-form',
    ]);
});

it('omits header actions from the payload when none are defined', function () {
    $payload = Table::make('plain')
        ->id('plain')
        ->columns([Column::make('id')])
        ->toArray();

    expect($payload)->not->toHaveKey('headerActions');
    expect($payload)->not->toHaveKey('actions');
});

it('omits the modal fields from a plain row action', function () {
    expect(Action::make('publish')->toArray())->toBe([
        'name' => 'publish',
        'label' => 'Publish',
    ]);
});

it('serves the header action through the table index endpoint', function () {
    $this->getJson('/refilament/table/posts')
        ->assertOk()
        ->assertJsonPath('headerActions.0.name', 'create')
        ->assertJsonPath('headerActions.0.type', 'create')
        ->assertJsonPath('headerActions.0.schema', 'post-form');
});

it('serves the posts schema document through the typed document endpoint', function () {
    $response = $this->getJson('/refilament/schema/post-form');

    $response->assertOk();
    $response->assertJsonPath('id', 'post-form');
    $response->assertJsonPath('contract', 1);
    $response->assertJsonCount(1, 'schema');
    $response->assertJsonPath('schema.0.type', 'section');

    // Initial data matches the full-page create form — the fields' defaults.
    $response->assertJsonPath('data', [
        'title' => null,
        'slug' => null,
        'author' => null,
        'status' => 'draft',
    ]);
    $response->assertJsonPath('errors', []);
});

it('serves the users schema document through the typed document endpoint', function () {
    $this->getJson('/refilament/schema/user-form')
        ->assertOk()
        ->assertJsonPath('id', 'user-form')
        ->assertJsonPath('contract', 1)
        ->assertJsonPath('data', [
            'name' => null,
            'email' => null,
            'password' => null,
        ]);
});

it('404s for an unknown schema document', function () {
    $this->getJson('/refilament/schema/not-a-schema')->assertNotFound();
});

it('submits through the same typed endpoint the modal reuses', function () {
    $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [
            'title' => 'Modal-created post',
            'slug' => 'modal-created-post',
            'author' => 'Ada Lovelace',
            'status' => 'published',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Post created.');

    expect(Post::where('slug', 'modal-created-post')->exists())->toBeTrue();
});

it('maps validation errors back into the modal form', function () {
    $this->postJson('/refilament/schema/post-form/submit', [
        'data' => ['title' => '', 'slug' => '', 'author' => '', 'status' => ''],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title.0', 'The Title field is required.');
});
