<?php

declare(strict_types=1);

use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tables\Column;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tests\Fixtures\ModalPostsTable;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Tables\PostsTable;

beforeEach(function () {
    app(Refilament::class)->registerTable(
        'modal-posts',
        static fn (): Table => ModalPostsTable::configure(Table::make()),
    );
});

it('serializes the modal create header action on a table that declares one', function () {
    $payload = ModalPostsTable::configure(Table::make())->toArray();

    expect($payload['headerActions'])->toHaveCount(1);
    expect($payload['headerActions'][0])->toBe([
        'name' => 'create',
        'label' => 'New Modal Post',
        'type' => 'create',
        'schema' => 'post-form',
    ]);
});

it('omits table-level header actions from the workbench resource tables', function () {
    // The resource list pages own create through the default page-header
    // CreateAction (slice 1.10) — the table-level modal surface moved to the
    // fixture tables, so the workbench tables ship no headerActions key.
    $payload = PostsTable::configure(Table::make())->toArray();

    expect($payload)->not->toHaveKey('headerActions');
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
    $this->getJson('/refilament/table/modal-posts')
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
        'user_id' => null,
        'status' => 'draft',
        'created_at' => null,
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
