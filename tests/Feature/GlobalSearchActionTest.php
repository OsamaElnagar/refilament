<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tests\Fixtures\SearchActionResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class SearchActionOwnerPolicy
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

beforeEach(function () {
    // Register only the fixture resource (registering the whole fixtures dir
    // would also pull DemoResource, whose plural label collides with the
    // workbench PostResource in the search categories).
    app(Refilament::class)->registerResources(SearchActionResource::class);
});

it('ships the resource key and record key on result hits', function () {
    // An explicit draft status keeps the actions deterministic: `unpublish`
    // is visible only for published records (the factory status is random).
    Post::factory()->create(['title' => 'Alpha post about widgets', 'status' => 'draft']);

    $response = $this->getJson('/refilament/search?q=widgets');

    $response->assertOk();
    $hits = collect($response->json('categories'))->flatten(1);
    $hit = $hits->firstWhere('resource', 'search-action');

    expect($hit)->not->toBeNull();
    expect($hit['record'])->not->toBeNull();
    // `pin` and `delete` are always visible; `unpublish` is hidden for a
    // draft record. The confirm-flagged action serializes its flag so the
    // client can pause before sending it.
    expect($hit['actions'])->toHaveCount(2);
    expect($hit['actions'][0]['name'])->toBe('pin');
    expect(collect($hit['actions'])->firstWhere('name', 'delete')['requiresConfirmation'])->toBeTrue();
    // The icon/tooltip pair (slice 3.5) serializes so the dialog can render
    // the named lucide icon and a hover hint next to the button.
    expect(collect($hit['actions'])->firstWhere('name', 'pin')['icon'])->toBe('pin');
    expect(collect($hit['actions'])->firstWhere('name', 'pin')['tooltip'])->toBe('Pin this post');
    expect(collect($hit['actions'])->firstWhere('name', 'delete')['icon'])->toBe('trash');
    expect(collect($hit['actions'])->firstWhere('name', 'delete')['tooltip'])->toBe('Delete this post');
});

it('runs a server-closure result action', function () {
    $post = Post::factory()->create(['title' => 'Alpha post about widgets', 'status' => 'draft']);

    $response = $this->postJson('/refilament/search/search-action/action/pin', [
        'record' => $post->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Post pinned.']);
    expect($post->fresh()->status)->toBe('published');
});

it('requires the record in the action request', function () {
    $this->postJson('/refilament/search/search-action/action/pin', [])->assertStatus(422);
});

it('rejects an unknown resource', function () {
    Post::factory()->create(['title' => 'Alpha']);

    $this->postJson('/refilament/search/nope/action/pin', ['record' => 1])->assertNotFound();
});

it('rejects an unknown record', function () {
    $this->postJson('/refilament/search/search-action/action/pin', ['record' => 999999])->assertNotFound();
});

it('rejects an unknown action', function () {
    $post = Post::factory()->create(['title' => 'Alpha post about widgets']);

    $this->postJson('/refilament/search/search-action/action/nope', ['record' => $post->id])
        ->assertNotFound();
});

it('rejects an action that is not visible for the record', function () {
    // unpublish() only renders for published records — the server must refuse
    // even when a stale client sends it.
    $post = Post::factory()->create(['title' => 'Alpha post about widgets', 'status' => 'draft']);

    $this->postJson('/refilament/search/search-action/action/unpublish', ['record' => $post->id])
        ->assertStatus(422)
        ->assertJson(['error' => 'Action is not available for this record.']);

    expect($post->fresh()->status)->toBe('draft');
});

it('refuses to run an action on a record the user cannot see', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $other = User::factory()->create(['email' => 'other@example.com']);

    $post = Post::factory()->create([
        'title' => 'Alpha post about widgets',
        'user_id' => $other->id,
    ]);

    Gate::policy(Post::class, SearchActionOwnerPolicy::class);

    // `$owner` can neither view nor edit the record, so it resolves no URL and
    // must never accept an action against it.
    $this->actingAs($owner)
        ->postJson('/refilament/search/search-action/action/pin', ['record' => $post->id])
        ->assertNotFound();
});
