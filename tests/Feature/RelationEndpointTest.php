<?php

declare(strict_types=1);

use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

it('serves only the owner record\'s related records', function () {
    $postA = Post::factory()->create();
    $postB = Post::factory()->create();
    Comment::factory()->count(3)->for($postA)->create();
    Comment::factory()->count(5)->for($postB)->create();

    $response = $this->getJson("/refilament/relation/posts/{$postA->id}/comments");

    $response->assertOk();
    $response->assertJsonPath('id', 'comments');
    $response->assertJsonPath('heading', 'Comments');
    $response->assertJsonPath('total', 3);
    $response->assertJsonCount(3, 'rows');

    $ids = collect($response->json('rows'))->pluck('id');
    expect($ids->intersect(Comment::query()->where('post_id', $postB->id)->pluck('id')))->toBeEmpty();
});

it('pages the owner\'s records like the table endpoint', function () {
    $post = Post::factory()->create();
    Comment::factory()->count(25)->for($post)->create();

    $pageOne = $this->getJson("/refilament/relation/posts/{$post->id}/comments?page=1")->json('rows');
    $pageTwo = $this->getJson("/refilament/relation/posts/{$post->id}/comments?page=2")->json('rows');

    expect($pageOne)->toHaveCount(10);
    expect($pageTwo)->toHaveCount(10);
    expect($pageTwo[0]['id'])->not->toBe($pageOne[0]['id']);
});

it('applies the table default sort scoped to the owner', function () {
    $post = Post::factory()->create();
    Comment::factory()->count(15)->for($post)->create();

    $response = $this->getJson("/refilament/relation/posts/{$post->id}/comments");

    $response->assertOk();
    expect($response->json('rows.0.id'))->toBe(
        Comment::query()->where('post_id', $post->id)->latest('id')->value('id'),
    );
});

it('sorts by an explicit sortable column within the owner', function () {
    $post = Post::factory()->create();
    Comment::factory()->count(5)->for($post)->create();

    $response = $this->getJson("/refilament/relation/posts/{$post->id}/comments?sort=title&direction=asc");

    $response->assertOk();
    expect($response->json('rows.0.title'))->toBe(
        Comment::query()->where('post_id', $post->id)->orderBy('title')->value('title'),
    );
});

it('searches only the owner\'s records', function () {
    $post = Post::factory()->create();
    $needle = Comment::factory()->for($post)->create()->title;
    Comment::factory()->count(3)->for($post)->create();
    $other = Post::factory()->create();
    Comment::factory()->create(['post_id' => $other->id, 'title' => $needle]);

    $response = $this->getJson("/refilament/relation/posts/{$post->id}/comments?search=".urlencode($needle));

    $response->assertOk();
    $rows = $response->json('rows');
    expect($rows)->not->toBeEmpty();
    expect(collect($rows)->pluck('id')->every(
        fn (int $id): bool => Comment::find($id)->post_id === $post->id,
    ))->toBeTrue();
});

it('applies a scoped select filter to the owner\'s records', function () {
    $post = Post::factory()->create();
    Comment::factory()->count(3)->for($post)->create(['is_visible' => true]);
    Comment::factory()->count(2)->for($post)->create(['is_visible' => false]);

    $response = $this->getJson("/refilament/relation/posts/{$post->id}/comments?filter[is_visible]=0");

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    // is_visible is a BooleanColumn — each row ships the structured
    // boolean display ({ value: Yes/No, icon, iconColor }).
    expect(array_column($response->json('rows'), 'is_visible'))->toBe([
        ['value' => 'No', 'icon' => 'x-circle', 'iconColor' => 'danger'],
        ['value' => 'No', 'icon' => 'x-circle', 'iconColor' => 'danger'],
    ]);
});

it('rejects an unknown resource', function () {
    $this->getJson('/refilament/relation/missing/1/comments')->assertNotFound();
});

it('rejects an unknown relation', function () {
    $post = Post::factory()->create();

    $this->getJson("/refilament/relation/posts/{$post->id}/missing")->assertNotFound();
});

it('rejects a missing owner record', function () {
    $this->getJson('/refilament/relation/posts/999999/comments')->assertNotFound();
});
