<?php

declare(strict_types=1);

use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

it('creates a comment owned by the given post', function () {
    $post = Post::factory()->create();

    $response = $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/create", [
        'data' => [
            'title' => 'Great post',
            'content' => 'Really enjoyed reading this.',
            'is_visible' => false,
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Comment added.']);

    $comment = Comment::latest('id')->first();
    expect($comment->post_id)->toBe($post->id)
        ->and($comment->title)->toBe('Great post')
        ->and($comment->is_visible)->toBeFalse();
});

it('validates create data against the manager form', function () {
    $post = Post::factory()->create();

    $response = $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/create", [
        'data' => ['content' => 'no title'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('title');
    expect(Comment::count())->toBe(0);
});

it('edits a comment through the manager module', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post)->create(['title' => 'Before']);

    $response = $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/edit", [
        'record' => $comment->id,
        'data' => ['title' => 'After', 'content' => 'edited'],
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Comment updated.']);
    expect($comment->fresh()->title)->toBe('After');
});

it('deletes a comment owned by the post', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post)->create();

    $response = $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/delete", [
        'record' => $comment->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Comment deleted.']);
    expect(Comment::find($comment->id))->toBeNull();
});

it('never lets a row action touch another owner\'s record', function () {
    $postA = Post::factory()->create();
    $postB = Post::factory()->create();
    $comment = Comment::factory()->for($postA)->create();

    // The record belongs to post A; addressing it through post B must fail
    // (the scoped query can't see it) and must not have deleted it.
    $response = $this->postJson("/refilament/relation/posts/{$postB->id}/comments/action/delete", [
        'record' => $comment->id,
    ]);

    $response->assertNotFound();
    expect(Comment::find($comment->id))->not->toBeNull();
});

it('rejects an unknown action', function () {
    $post = Post::factory()->create();

    $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/nope", [
        'record' => 1,
    ])->assertNotFound();
});

it('rejects an unknown relation', function () {
    $post = Post::factory()->create();

    $this->postJson("/refilament/relation/posts/{$post->id}/tags/action/create", ['data' => []])
        ->assertNotFound();
});

it('rejects a missing owner record', function () {
    $this->postJson('/refilament/relation/posts/999999/comments/action/create', ['data' => []])
        ->assertNotFound();
});

it('requires a record for a row action', function () {
    $post = Post::factory()->create();

    $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/delete", [])
        ->assertUnprocessable();
});
