<?php

declare(strict_types=1);

use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Tables\PostsTable;

beforeEach(function () {
    // The Post factory predates the form's slug rule and leaves it null —
    // these tests edit through that rule, so every post needs a real slug.
    Post::factory()->count(45)->create([
        'slug' => fn (): string => fake()->unique()->slug(),
    ]);
    User::factory()->count(3)->create();
});

it('serializes the modal edit row action on the posts table', function () {
    $payload = PostsTable::configure(Table::make())->toArray();

    expect($payload['actions'])->toHaveCount(3);

    $edit = collect($payload['actions'])->firstWhere('name', 'edit');

    expect($edit)->toBe([
        'name' => 'edit',
        'label' => 'Edit',
        'type' => 'edit',
        'schema' => 'post-form',
    ]);
});

it('serves a record pre-filled into its form fields', function () {
    $post = Post::firstOrFail();

    $this->getJson('/refilament/table/posts/record/'.$post->id.'?schema=post-form')
        ->assertOk()
        ->assertJsonPath('data.title', $post->title)
        ->assertJsonPath('data.slug', $post->slug)
        ->assertJsonPath('data.author', $post->author)
        ->assertJsonPath('data.status', $post->status);
});

it('returns only the form fields, never other record attributes', function () {
    $post = Post::firstOrFail();

    $this->getJson('/refilament/table/posts/record/'.$post->id.'?schema=post-form')
        ->assertOk()
        ->assertJsonMissingPath('data.views')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.published_at');
});

it('never pre-fills a password field with the stored hash', function () {
    $user = User::firstOrFail();

    $this->getJson('/refilament/table/users/record/'.$user->id.'?schema=user-form')
        ->assertOk()
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.password', '');
});

it('404s the record fetch for an unknown table, schema or record', function () {
    $this->getJson('/refilament/table/nope/record/1?schema=post-form')->assertNotFound();
    $this->getJson('/refilament/table/posts/record/1?schema=nope')->assertNotFound();
    $this->getJson('/refilament/table/posts/record/999999?schema=post-form')->assertNotFound();
    $this->getJson('/refilament/table/posts/record/1')->assertUnprocessable(); // missing schema
});

it('updates a record through the edit action with validated data', function () {
    $post = Post::firstOrFail();

    $this->postJson('/refilament/table/posts/action/edit', [
        'record' => $post->id,
        'data' => [
            'title' => 'Edited title',
            'slug' => $post->slug,
            'author' => $post->author,
            'status' => $post->status,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Post updated.');

    expect($post->refresh()->title)->toBe('Edited title');
});

it('accepts an unchanged unique slug when editing a record', function () {
    $post = Post::firstOrFail();

    // The slug rule is `unique:posts,slug` — without the edit ignore it
    // would reject the record's own value against itself.
    $this->postJson('/refilament/table/posts/action/edit', [
        'record' => $post->id,
        'data' => [
            'title' => $post->title,
            'slug' => $post->slug,
            'author' => $post->author,
            'status' => $post->status,
        ],
    ])->assertOk();
});

it('maps edit validation errors back onto the fields', function () {
    $post = Post::firstOrFail();

    $this->postJson('/refilament/table/posts/action/edit', [
        'record' => $post->id,
        'data' => ['title' => '', 'slug' => '', 'author' => '', 'status' => ''],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title.0', 'The Title field is required.')
        ->assertJsonPath('errors.slug.0', 'The Slug field is required.');
});

it('rejects an edit that violates a unique rule', function () {
    $first = Post::firstOrFail();
    $second = Post::skip(1)->firstOrFail();

    $this->postJson('/refilament/table/posts/action/edit', [
        'record' => $first->id,
        'data' => [
            'title' => $first->title,
            'slug' => $second->slug, // already taken by the second post
            'author' => $first->author,
            'status' => $first->status,
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slug.0', 'The Slug has already been taken.');
});

it('deletes a user through the confirmed row action', function () {
    $user = User::firstOrFail();

    $this->postJson('/refilament/table/users/action/delete', ['record' => $user->id])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User deleted.');

    expect(User::find($user->id))->toBeNull();
});

it('still runs plain row actions without a data payload', function () {
    $post = Post::factory()->create(['status' => 'draft']);

    $this->postJson('/refilament/table/posts/action/publish', ['record' => $post->id])
        ->assertOk()
        ->assertJsonPath('message', 'Post published.');

    expect($post->refresh()->status)->toBe('published');
});
