<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tests\Fixtures\RecordManageResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Pages\PostReadPage;

/**
 * Repeatable entry (slice: RepeatableEntry / PLAN §3) — the resource view page
 * (PostResource::infolist, served by ViewRecord) ships each repeatable item's
 * child entries resolved server-side against that item's array data.
 */
it('serializes a repeatable entry against each item on the resource view page', function () {
    $post = Post::factory()->create(['title' => 'Hello World']);

    $this->get("/refilament/posts/{$post->id}", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-view')
        ->assertJsonPath('props.schema.7.name', 'word_bag')
        ->assertJsonPath('props.schema.7.label', 'Word breakdown')
        ->assertJsonPath('props.schema.7.items', [
            [
                ['type' => 'text_entry', 'name' => 'word', 'label' => 'Word', 'value' => 'Hello'],
                ['type' => 'text_entry', 'name' => 'length', 'label' => 'Length', 'value' => '5'],
            ],
            [
                ['type' => 'text_entry', 'name' => 'word', 'label' => 'Word', 'value' => 'World'],
                ['type' => 'text_entry', 'name' => 'length', 'label' => 'Length', 'value' => '5'],
            ],
        ]);
});

/**
 * Fixture policy for the record-pages authorization test — a user may view /
 * update only their own posts (mirrors the slice 4.1 OwnerPostPolicy).
 */
class RecordOwnerPostPolicy
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

/**
 * Phase 3 of the custom-pages program — page infolists and record-scoped
 * custom pages (docs/ROADMAP.md "3.6 Page infolists" / "record pages").
 *
 * A page hosting a read-only infolist declares `infolist()` and renders the
 * generic refilament/page-infolist component; the entries resolve their
 * values from the record the page reads (the URL record on a record-scoped
 * `/{record}/manage` page, the page's own getInfolistRecord() on a
 * standalone page). A record-scoped page hosting a `form()` pre-fills from
 * the URL record and saves through the record-bound submit endpoint.
 */
it('serializes the page infolist payload on a standalone page', function () {
    $post = Post::factory()->create(['title' => 'The Standalone Post']);

    $this->get('/refilament/latest-post', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-infolist')
        ->assertJsonPath('props.infolistTitle', 'Latest post')
        ->assertJsonPath('props.infolistId', PostReadPage::getInfolistId())
        ->assertJsonPath('props.description', 'A page hosting a read-only infolist — entries resolve their values from the latest post server-side.')
        // The entries resolve their values from the page's record (the
        // latest post — the only one in this test).
        ->assertJsonCount(5, 'props.schema')
        ->assertJsonPath('props.schema.0.name', 'title')
        ->assertJsonPath('props.schema.0.value', $post->title)
        ->assertJsonPath('props.schema.1.value', $post->status);
});

it('serializes a record-scoped manage page infolist bound to the URL record', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create(['title' => 'Managed Post', 'author' => 'Ada Lovelace', 'status' => 'published']);

    $this->get("/refilament/record-manage/{$post->id}/manage", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-infolist')
        ->assertJsonPath('props.record', $post->id)
        ->assertJsonPath('props.infolistTitle', 'Record Manage Page')
        // The entries read the URL record, not the page's own record hook.
        ->assertJsonCount(4, 'props.schema')
        ->assertJsonPath('props.schema.0.name', 'title')
        ->assertJsonPath('props.schema.0.value', 'Managed Post')
        ->assertJsonPath('props.schema.2.value', 'Ada Lovelace');
});

it('pre-fills a record-scoped form page from the URL record and ships the submit endpoint', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create(['title' => 'Original', 'author' => 'Ada Lovelace', 'status' => 'draft', 'slug' => 'original']);

    $this->get("/refilament/record-manage/{$post->id}/settings", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-form')
        ->assertJsonPath('props.record', $post->id)
        ->assertJsonPath('props.data.title', 'Original')
        ->assertJsonPath('props.data.author', 'Ada Lovelace')
        ->assertJsonPath('props.data.status', 'draft')
        ->assertJsonPath('props.hasUnsavedDataChangesAlert', true)
        // The record-bound submit endpoint — the client posts the form there
        // so the save validates + updates the URL record server-side.
        ->assertJsonPath('props.submitUrl', route('refilament.resource.page-form', [
            'resource' => 'record-manage',
            'page' => 'settings',
            'record' => $post->id,
        ]));
});

it('submits a record-scoped page form through the record-bound endpoint and persists', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create(['title' => 'Old Title', 'author' => 'Ada Lovelace', 'status' => 'draft']);

    $this->actingAs(User::factory()->create())
        ->postJson("/refilament/record-manage/page/settings/record/{$post->id}/submit", [
            'data' => ['title' => 'New Title', 'author' => 'Ada Lovelace', 'slug' => 'new-slug', 'status' => 'published'],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        // A record-scoped page form that declares no updateSuccessMessage()
        // still gets the default 'Saved.' toast (the form stays on the page).
        ->assertJsonPath('message', 'Saved.')
        ->assertJsonPath('data.title', 'New Title')
        ->assertJsonPath('data.status', 'published');

    expect($post->fresh())->title->toBe('New Title')
        ->and($post->fresh())->slug->toBe('new-slug')
        ->and($post->fresh())->status->toBe('published');
});

it('validates record-scoped page form submissions against the form rules', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create(['title' => 'Old Title', 'author' => 'Ada Lovelace', 'status' => 'draft']);

    $this->actingAs(User::factory()->create())
        ->postJson("/refilament/record-manage/page/settings/record/{$post->id}/submit", [
            'data' => ['title' => '', 'author' => 'Ada Lovelace', 'status' => 'draft'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.title.0', 'The Title field is required.');

    // Nothing was persisted on a failed submission.
    expect($post->fresh())->title->toBe('Old Title');
});

it('never rejects the record\'s own unique values on a record-scoped page form', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create(['title' => 'Original', 'author' => 'Ada Lovelace', 'status' => 'draft', 'slug' => 'my-post']);

    // Saving the record's own slug must not fail the unique rule — the
    // record-bound endpoint ignores the record being edited, the same
    // rewrite the typed record update endpoint applies.
    $this->actingAs(User::factory()->create())
        ->postJson("/refilament/record-manage/page/settings/record/{$post->id}/submit", [
            'data' => ['title' => 'Renamed', 'author' => 'Ada Lovelace', 'slug' => 'my-post', 'status' => 'draft'],
        ])
        ->assertOk();

    expect($post->fresh())->title->toBe('Renamed')
        ->and($post->fresh())->slug->toBe('my-post');
});

it('gates record-scoped pages and submissions with the resource policy', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    Gate::policy(Post::class, RecordOwnerPostPolicy::class);

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $owner->id, 'title' => 'Owned', 'author' => 'Ada', 'status' => 'draft']);

    // The owner may view and edit their own record page.
    $this->actingAs($owner)
        ->get("/refilament/record-manage/{$post->id}/settings", ['X-Inertia' => 'true'])
        ->assertOk();

    // Another user cannot view the record page...
    $this->actingAs($other)
        ->get("/refilament/record-manage/{$post->id}/manage", ['X-Inertia' => 'true'])
        ->assertForbidden();

    // ...and cannot submit through the record-bound endpoint either.
    $this->actingAs($other)
        ->postJson("/refilament/record-manage/page/settings/record/{$post->id}/submit", [
            'data' => ['title' => 'Hacked', 'author' => 'Ada', 'status' => 'published'],
        ])
        ->assertForbidden();
});

it('404s a record-scoped page whose record is gone', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $this->get('/refilament/record-manage/999/manage', ['X-Inertia' => 'true'])
        ->assertNotFound();
});

it('serves the workbench manage page bound to the record', function () {
    $post = Post::factory()->create(['title' => 'Managed By Workbench', 'status' => 'published']);

    $this->get("/refilament/posts/{$post->id}/manage", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-infolist')
        ->assertJsonPath('props.record', $post->id)
        ->assertJsonPath('props.schema.0.value', 'Managed By Workbench')
        ->assertJsonPath('props.schema.1.value', 'published')
        // The record page's Edit/Delete header actions serialize.
        ->assertJsonCount(2, 'props.pageActions');
});

it('omits the infolist payload from pages that declare no infolist', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordManageResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create(['title' => 'Post', 'author' => 'Ada', 'status' => 'draft']);

    // The settings page hosts a form — no infolist keys.
    $this->get("/refilament/record-manage/{$post->id}/settings", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.infolistTitle')
        ->assertJsonMissingPath('props.infolistId');

    // The manage page hosts an infolist — no form keys.
    $this->get("/refilament/record-manage/{$post->id}/manage", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.formTitle')
        ->assertJsonMissingPath('props.submitUrl')
        ->assertJsonMissingPath('props.data');
});
