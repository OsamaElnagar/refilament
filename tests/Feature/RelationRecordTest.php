<?php

declare(strict_types=1);

use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

it('pre-fills a related record for the manager form', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post)->create([
        'title' => 'Hello',
        'content' => 'A comment body.',
        'is_visible' => false,
    ]);

    $response = $this->getJson("/refilament/relation/posts/{$post->id}/comments/record/{$comment->id}?schema=comment-form");

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'title' => 'Hello',
            'content' => 'A comment body.',
            'is_visible' => false,
        ],
    ]);
});

it('never pre-fills another owner\'s record', function () {
    $postA = Post::factory()->create();
    $postB = Post::factory()->create();
    $comment = Comment::factory()->for($postA)->create();

    // The record belongs to post A; addressing it through post B must fail
    // (the scoped query can't see it) rather than leak its values.
    $this->getJson("/refilament/relation/posts/{$postB->id}/comments/record/{$comment->id}?schema=comment-form")
        ->assertNotFound();
});

it('requires the schema to pre-fill', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post)->create();

    $this->getJson("/refilament/relation/posts/{$post->id}/comments/record/{$comment->id}")
        ->assertUnprocessable();
});

it('rejects an unknown relation when pre-filling', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post)->create();

    $this->getJson("/refilament/relation/posts/{$post->id}/tags/record/{$comment->id}?schema=comment-form")
        ->assertNotFound();
});

it('rejects a missing owner record when pre-filling', function () {
    $this->getJson('/refilament/relation/posts/999999/comments/record/1?schema=comment-form')
        ->assertNotFound();
});
