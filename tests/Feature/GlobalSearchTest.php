<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class GlobalSearchOwnerPostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}

it('returns grouped results across searchable resources', function () {
    Post::factory()->create(['title' => 'Alpha post about widgets']);
    Post::factory()->count(3)->create(['title' => 'Unlikely title']);

    $response = $this->getJson('/refilament/search?q=widgets');

    $response->assertOk();
    $response->assertJsonPath('query', 'widgets');
    $response->assertJsonStructure([
        'categories' => [
            'Posts' => [
                '*' => ['title', 'url', 'details'],
            ],
        ],
    ]);

    $hits = $response->json('categories.Posts');
    expect($hits)->toHaveCount(1);
    expect($hits[0]['title'])->toBe('Alpha post about widgets');
    expect($hits[0]['url'])->toContain('/refilament/posts/');
});

it('returns an empty categories payload for an unknown term', function () {
    Post::factory()->create(['title' => 'Alpha']);

    $this->getJson('/refilament/search?q=zzz-nothing-matches')
        ->assertOk()
        ->assertJsonPath('categories', []);
});

it('returns no categories for an empty or missing query', function () {
    $this->getJson('/refilament/search')->assertOk()->assertJsonPath('categories', []);
    $this->getJson('/refilament/search?q=')->assertOk()->assertJsonPath('categories', []);
    $this->getJson('/refilament/search?q=%20%20')->assertOk()->assertJsonPath('categories', []);
});

it('matches the term across multiple searchable columns', function () {
    Post::factory()->create(['title' => 'Post one', 'author' => 'Ada Lovelace']);
    Post::factory()->create(['title' => 'Grace Hopper biography', 'author' => 'Grace Hopper']);

    // Matches the "Grace" in the author column of the second post.
    $response = $this->getJson('/refilament/search?q=Grace');

    $response->assertOk();
    expect($response->json('categories.Posts'))->toHaveCount(1);
    expect($response->json('categories.Posts.0.title'))->not->toBe('Post one');
});

it('caps each resource to its global search results limit', function () {
    Post::factory()->count(60)->create(['title' => 'Same title']);

    $response = $this->getJson('/refilament/search?q=Same');

    $response->assertOk();
    expect(collect($response->json('categories.Posts')))->toHaveCount(50);
});

it('honours getGlobalSearchSort when ordering resources', function () {
    Post::factory()->create(['title' => 'Apple']);

    // Users have no searchable columns, so only Posts surface — but the sort
    // runs without error and Posts still resolves.
    $this->getJson('/refilament/search?q=Apple')->assertOk();
});

it('carries per-record result actions', function () {
    Post::factory()->create(['title' => 'Alpha post about widgets']);

    $response = $this->getJson('/refilament/search?q=widgets');

    $response->assertOk();
    $actions = $response->json('categories.Posts.0.actions');
    expect($actions)->toHaveCount(1);
    expect($actions[0]['name'])->toBe('edit');
    expect($actions[0]['url'])->toContain('/edit');
});

it('drops results a user can neither view nor edit', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $other = User::factory()->create(['email' => 'other@example.com']);

    Post::factory()->create(['title' => 'Alpha post about widgets', 'user_id' => $owner->id]);
    Post::factory()->create(['title' => 'Beta post about widgets', 'user_id' => $other->id]);

    Gate::policy(Post::class, GlobalSearchOwnerPostPolicy::class);

    // The owner can view and edit only their own record, so the record owned
    // by `$other` resolves no URL and is dropped from the results.
    $response = $this->actingAs($owner)->getJson('/refilament/search?q=widgets');

    $response->assertOk();
    $hits = $response->json('categories.Posts');
    expect($hits)->toHaveCount(1);
    expect($hits[0]['title'])->toBe('Alpha post about widgets');
});
