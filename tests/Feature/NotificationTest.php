<?php

declare(strict_types=1);

use Workbench\App\Models\Post;

it('emits the rich notification on the create-form submit', function () {
    $response = $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [
            'title' => 'Hello world',
            'slug' => 'hello-world',
            'author' => 'Ada Lovelace',
            'status' => 'draft',
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'notification' => [
                'title' => 'Post created.',
                'body' => 'Your post is now live in the listing.',
                'status' => 'success',
            ],
            // The flat message stays for backward compatibility.
            'message' => 'Post created.',
        ]);
});

it('emits the success notification on the record update', function () {
    $post = Post::factory()->create([
        'status' => 'draft',
    ]);

    $response = $this->postJson("/refilament/table/posts/record/{$post->id}", [
        'data' => [
            'title' => 'Edited title',
            'slug' => 'edited-title',
            'author' => 'Ada Lovelace',
            'status' => 'published',
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'notification' => [
                'title' => 'Post updated.',
                'body' => 'The changes have been saved.',
                'status' => 'success',
            ],
        ]);
});

it('emits the success notification on the bulk delete action', function () {
    $posts = Post::factory()->count(2)->create();

    $response = $this->postJson('/refilament/table/posts/bulk/delete', [
        'records' => $posts->pluck('id')->all(),
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'notification' => [
                'title' => 'Posts deleted.',
                'body' => 'The selected posts were removed.',
                'status' => 'danger',
            ],
        ]);
});
