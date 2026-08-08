<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Resources\PostResource;

/**
 * Slice 4.1 — authorization (docs/ROADMAP.md "4.1 Authorization").
 *
 * Resource access delegates to Laravel Model Policies through the Gate,
 * mirroring Filament's HasAuthorization. Without a policy the default is
 * permissive (a fresh install with no policies stays open — the workbench
 * stays open); the moment a policy declares an ability, the Gate decides for
 * the current user.
 */
class ModOnlyPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->email === 'mod@example.com';
    }

    public function create(User $user): bool
    {
        return true;
    }
}

class OwnerPostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}

class DenyAllPostPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }
}

class GrantAllPostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return true;
    }
}

it('allows access by default when no policy exists', function () {
    expect(PostResource::canAccess())->toBeTrue()
        ->and(PostResource::canViewAny())->toBeTrue()
        ->and(PostResource::canCreate())->toBeTrue();
});

it('canAccess delegates to canViewAny, which consults the policy', function () {
    $moderator = User::factory()->create(['email' => 'mod@example.com']);
    $guest = User::factory()->create(['email' => 'guest@example.com']);

    Gate::policy(Post::class, ModOnlyPostPolicy::class);

    $this->actingAs($moderator);
    expect(PostResource::canViewAny())->toBeTrue()
        ->and(PostResource::canAccess())->toBeTrue()
        ->and(PostResource::canCreate())->toBeTrue();

    $this->actingAs($guest);
    expect(PostResource::canViewAny())->toBeFalse()
        ->and(PostResource::canAccess())->toBeFalse()
        ->and(PostResource::canCreate())->toBeTrue();
});

it('gates record abilities (view/update/delete) against the policy', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $owned = Post::factory()->create(['user_id' => $owner->id]);
    $foreign = Post::factory()->create(['user_id' => $other->id]);

    Gate::policy(Post::class, OwnerPostPolicy::class);

    $this->actingAs($owner);

    expect(PostResource::canView($owned))->toBeTrue()
        ->and(PostResource::canEdit($owned))->toBeTrue()
        ->and(PostResource::canDelete($owned))->toBeTrue()
        ->and(PostResource::canView($foreign))->toBeFalse()
        ->and(PostResource::canEdit($foreign))->toBeFalse()
        ->and(PostResource::canDelete($foreign))->toBeFalse();
});

it('rejects a user from a page they cannot access', function () {
    $guest = User::factory()->create(['email' => 'guest@example.com']);

    Gate::policy(Post::class, DenyAllPostPolicy::class);

    $this->actingAs($guest)
        ->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertForbidden();
});

it('rejects a user from an edit page for a record they cannot update', function () {
    $other = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $other->id]);
    $guest = User::factory()->create(['email' => 'guest@example.com']);

    Gate::policy(Post::class, OwnerPostPolicy::class);

    // The owner's own records are editable; the guest's are not.
    $this->actingAs($other)
        ->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-edit');

    $this->actingAs($guest)
        ->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
        ->assertForbidden();
});

it('lets an authorized user through the pages', function () {
    $owner = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id]);

    Gate::policy(Post::class, GrantAllPostPolicy::class);

    $this->actingAs($owner);

    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-table');

    $this->get('/refilament/posts/create', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-create');

    $this->get("/refilament/posts/{$post->id}", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-view');

    $this->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-edit');
});

it('hides inaccessible resources from the sidebar navigation', function () {
    $guest = User::factory()->create(['email' => 'guest@example.com']);

    Gate::policy(Post::class, DenyAllPostPolicy::class);

    $this->actingAs($guest);

    $panel = $this->get('/refilament', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    $labels = collect($panel['items'])->pluck('label')->all();

    expect($labels)->not->toContain('Posts');
});

it('keeps accessible resources in the sidebar navigation', function () {
    $owner = User::factory()->create();

    Gate::policy(Post::class, GrantAllPostPolicy::class);

    $this->actingAs($owner);

    $panel = $this->get('/refilament', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    $labels = collect($panel['items'])->pluck('label')->all();

    expect($labels)->toContain('Posts');
});
