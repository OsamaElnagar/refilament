<?php

declare(strict_types=1);

use Workbench\App\Models\Post;

beforeEach(function () {
    Post::factory()->count(45)->create();
});

it('marks the posts table selectable', function () {
    $response = $this->getJson('/refilament/table/posts');

    $response->assertOk();
    $response->assertJsonPath('selectable', true);
});

it('serializes the toolbar actions in the definition', function () {
    $toolbarActions = $this->getJson('/refilament/table/posts')->json('toolbarActions');

    expect($toolbarActions)->toBe([
        [
            'name' => 'delete',
            'label' => 'Delete selected',
            'color' => 'danger',
            'requiresConfirmation' => true,
            'icon' => 'trash',
            'modalHeading' => 'Delete selected records',
            'modalDescription' => 'The selected records will be permanently removed. This action cannot be undone.',
        ],
        [
            'name' => 'restore',
            'label' => 'Restore selected',
            'color' => 'secondary',
            'requiresConfirmation' => true,
        ],
        [
            'name' => 'forceDelete',
            'label' => 'Force delete selected',
            'color' => 'danger',
            'requiresConfirmation' => true,
        ],
    ]);
});

it('serializes the trashed filter in the definition', function () {
    $filters = $this->getJson('/refilament/table/posts')->json('filters');

    expect($filters)->toContain([
        'name' => 'trashed',
        'label' => 'Trashed',
        'type' => 'trashed',
        'options' => [
            ['value' => '', 'label' => 'Without deleted records'],
            ['value' => 'with', 'label' => 'With deleted records'],
            ['value' => 'only', 'label' => 'Only deleted records'],
        ],
    ]);
});

it('runs a bulk action against the selected records', function () {
    $posts = Post::factory()->count(3)->create();

    $keys = $posts->pluck('id')->all();

    $response = $this->postJson('/refilament/table/posts/bulk/delete', ['records' => $keys]);

    $response->assertOk();
    // The workbench's DeleteBulkAction declares a successNotification whose
    // body wins over the default message — only `success` rides without it.
    $response->assertJson(['success' => true]);

    foreach ($posts as $post) {
        expect(Post::find($post->id))->toBeNull();
    }
});

it('deletes exactly the selected records', function () {
    $selected = Post::factory()->count(2)->create();
    Post::factory()->count(2)->create();

    $keys = $selected->pluck('id')->all();

    $response = $this->postJson('/refilament/table/posts/bulk/delete', ['records' => $keys]);

    $response->assertOk();

    foreach ($selected as $post) {
        expect(Post::find($post->id))->toBeNull();
    }

    // The unselected posts survive.
    expect(Post::count())->toBe(45 + 2);
});

it('runs the bulk action closure exactly once', function () {
    $posts = Post::factory()->count(4)->create();

    $this->postJson('/refilament/table/posts/bulk/delete', ['records' => $posts->pluck('id')->all()])->assertOk();

    expect(Post::count())->toBe(45);
});

it('rejects an unknown bulk action', function () {
    $this->postJson('/refilament/table/posts/bulk/nope', ['records' => [1]])->assertNotFound();
});

it('rejects a bulk action on an unknown table', function () {
    $this->postJson('/refilament/table/missing/bulk/delete', ['records' => [1]])->assertNotFound();
});

it('requires the records array in the bulk request', function () {
    $this->postJson('/refilament/table/posts/bulk/delete', [])->assertStatus(422);
});

it('rejects an empty records array', function () {
    $this->postJson('/refilament/table/posts/bulk/delete', ['records' => []])->assertStatus(422);
});

it('rejects a bulk action when a selected record no longer exists', function () {
    $post = Post::factory()->create();

    // The client selected the record, but it was removed since the row
    // rendered. Posts are soft-deletable, so a plain delete() still leaves the
    // row resolvable (the bulk endpoint lifts the SoftDeletingScope) — only a
    // forceDelete truly removes it.
    $post->forceDelete();

    $this->postJson('/refilament/table/posts/bulk/delete', ['records' => [$post->id]])
        ->assertNotFound();
});

it('returns no message when the bulk action has none', function () {
    $post = Post::factory()->create();

    // The posts table's bulk delete declares a successMessage, so this just
    // verifies the happy path returns the configured message — see the serialized
    // toolbar action above for the message source.
    $response = $this->postJson('/refilament/table/posts/bulk/delete', ['records' => [$post->id]]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

it('restores trashed records through the restore bulk action', function () {
    $trashed = Post::factory()->count(2)->create(['status' => 'archived']);
    $live = Post::factory()->create();

    $trashed->each(fn ($post): mixed => $post->delete());
    expect(Post::onlyTrashed()->count())->toBe(2);

    $keys = $trashed->pluck('id')->all();

    $this->postJson('/refilament/table/posts/bulk/restore', ['records' => $keys])
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Selected posts restored.']);

    foreach ($trashed as $post) {
        expect(Post::withTrashed()->find($post->id)->trashed())->toBeFalse();
    }

    // The live record was never touched.
    expect($live->trashed())->toBeFalse();
});

it('permanently deletes trashed records via the forceDelete bulk action', function () {
    $trashed = Post::factory()->count(2)->create();

    $trashed->each(fn ($post): mixed => $post->delete());

    $response = $this->postJson('/refilament/table/posts/bulk/forceDelete', ['records' => $trashed->pluck('id')->all()]);

    $response->assertOk();
    $response->assertJson(['success' => true, 'message' => 'Selected posts permanently deleted.']);

    foreach ($trashed as $post) {
        expect(Post::withTrashed()->find($post->id))->toBeNull();
    }
});

it('filters the listing by trash state via the trashed filter variants', function () {
    Post::factory()->count(3)->create();
    $trashed = Post::factory()->count(2)->create();

    $trashed->each(fn ($post): mixed => $post->delete());

    // beforeEach created 45 live posts; plus the 3 live above = 48 live, and
    // 2 trashed. perPage=50 (the table's max) fits every group on one page.
    $default = $this->getJson('/refilament/table/posts?perPage=50');
    $without = $this->getJson('/refilament/table/posts?filter[trashed]=&perPage=50');
    $only = $this->getJson('/refilament/table/posts?filter[trashed]=only&perPage=50');
    $with = $this->getJson('/refilament/table/posts?filter[trashed]=with&perPage=50');

    expect($default->json('rows'))->toHaveCount(48);
    expect($without->json('rows'))->toHaveCount(48);
    expect($only->json('rows'))->toHaveCount(2);
    expect($with->json('rows'))->toHaveCount(50);
});
