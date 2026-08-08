<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tests\Fixtures\ProtectedPostsTable;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class LockedCommentsPolicy
{
    // Guest-opt-in first parameter (Laravel 13), mirroring the fixture policy
    // in the unit tests — the denial below is the policy's own decision.
    public function create(?User $user = null): bool
    {
        return false;
    }
}

beforeEach(function () {
    ProtectedPostsTable::$allow = false;

    app(Refilament::class)->registerTable(
        'protected-posts',
        static fn (): Table => ProtectedPostsTable::configure(Table::make()),
    );
});

it('omits an unauthorized header action from the serialized table', function () {
    $this->getJson('/refilament/table/protected-posts')
        ->assertOk()
        ->assertJsonMissingPath('headerActions');
});

it('omits an unauthorized toolbar action from the serialized table', function () {
    $this->getJson('/refilament/table/protected-posts')
        ->assertOk()
        ->assertJsonMissingPath('toolbarActions');
});

it('serializes header and toolbar actions once authorized', function () {
    ProtectedPostsTable::$allow = true;

    $this->getJson('/refilament/table/protected-posts')
        ->assertOk()
        ->assertJsonPath('headerActions.0.name', 'create')
        ->assertJsonPath('toolbarActions.0.name', 'wipe');
});

it('refuses an unauthorized bulk action at the endpoint', function () {
    $posts = Post::factory()->count(3)->create();

    $this->postJson('/refilament/table/protected-posts/bulk/wipe', [
        'records' => $posts->pluck('id')->all(),
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'Action is not available.');

    // The denial is authoritative — nothing ran.
    expect(Post::query()->count())->toBe(3);
});

it('runs an authorized bulk action at the endpoint', function () {
    ProtectedPostsTable::$allow = true;

    $posts = Post::factory()->count(3)->create();

    $this->postJson('/refilament/table/protected-posts/bulk/wipe', [
        'records' => $posts->pluck('id')->all(),
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect(Post::query()->count())->toBe(0);
});

it('refuses an unauthorized row action at the endpoint', function () {
    $post = Post::factory()->create();

    $this->postJson('/refilament/table/protected-posts/action/delete', [
        'record' => $post->id,
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'Action is not available for this record.');
});

it('runs an authorized row action at the endpoint', function () {
    ProtectedPostsTable::$allow = true;

    $post = Post::factory()->create();

    $this->postJson('/refilament/table/protected-posts/action/delete', [
        'record' => $post->id,
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect(Post::query()->find($post->id))->toBeNull();
});

it('refuses an unauthorized relation header create at the endpoint', function () {
    // The workbench comments relation manager gates its create action with
    // authorize('create', Comment::class) — a policy that declares and denies
    // create must stop the endpoint, even though the client may still send it.
    Gate::policy(Comment::class, LockedCommentsPolicy::class);

    try {
        $post = Post::factory()->create();

        $this->postJson("/refilament/relation/posts/{$post->id}/comments/action/create", [
            'data' => [
                'title' => 'Sneaky comment',
                'content' => 'Should never be created.',
                'is_visible' => false,
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'Action is not available.');

        expect(Comment::count())->toBe(0);
    } finally {
        Gate::policy(Comment::class, null);
    }
});
