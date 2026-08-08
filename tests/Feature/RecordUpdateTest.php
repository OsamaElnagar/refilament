<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Resources\PostResource;

beforeEach(function () {
    Post::factory()->count(20)->create(['status' => 'published']);
    Post::factory()->count(5)->create(['status' => 'draft']);
});

it('serializes the summarize flag on summarized columns only', function () {
    $response = $this->getJson('/refilament/table/posts');

    $response->assertOk();

    $columns = $response->json('columns');
    $views = collect($columns)->firstWhere('name', 'views');
    $status = collect($columns)->firstWhere('name', 'status');

    expect($views['summarized'])->toBeTrue();
    expect($status)->not->toHaveKey('summarized');
});

it('computes footer summaries over the filtered query', function () {
    // 20 published posts, 5 draft — the summary must reflect the whole
    // filtered set, not the 10-row page.
    $response = $this->getJson('/refilament/table/posts?filter[status]=published');

    $response->assertOk();

    $expected = Post::where('status', 'published')->sum('views');
    $summary = $response->json('summary.views');

    expect($summary)->toHaveCount(1);
    expect($summary[0]['label'])->toBe('Total views');
    // Formatted server-side by the summarizer's numeric() — grouped thousands.
    expect($summary[0]['value'])->toBe(number_format((float) $expected));
});

it('omits the summary key when no column is summarized', function () {
    $this->getJson('/refilament/table/users')->assertOk()->assertJsonMissingPath('summary');
});

it('serves the infolist schema on the view page when the resource defines one', function () {
    $post = Post::first();

    $response = $this->get("/refilament/posts/{$post->id}", ['X-Inertia' => 'true']);

    $response->assertOk()
        ->assertJsonPath('component', 'refilament/resource-view')
        ->assertJsonPath('props.schema.0.name', 'title')
        ->assertJsonPath('props.schema.0.value', $post->title);

    $status = collect($response->json('props.schema'))->firstWhere('name', 'status');
    $views = collect($response->json('props.schema'))->firstWhere('name', 'views');

    expect($status['badge'])->toBeTrue();
    expect($status['color'])->toBeString();
    // Numeric formatting happens server-side (grouped thousands).
    expect($views['value'])->toBe(number_format((float) $post->views));
});

it('falls back to columns + values + summaries on the view page without an infolist', function () {
    Post::factory()->create();

    $user = User::first();
    $response = $this->get("/refilament/users/{$user->id}", ['X-Inertia' => 'true']);

    $response->assertOk()
        ->assertJsonPath('component', 'refilament/resource-view')
        ->assertJsonMissingPath('props.schema.0')
        ->assertJsonPath('props.values.name', $user->name);
});

it('updates a record through the typed update endpoint', function () {
    $post = Post::factory()->create(['slug' => 'update-me', 'status' => 'draft']);

    $this->postJson("/refilament/table/posts/record/{$post->id}", [
        'data' => [
            'title' => 'Updated title',
            'slug' => 'update-me',
            'author' => 'Ada Lovelace',
            'status' => 'published',
        ],
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Post updated.')
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.status', 'published');

    $post->refresh();
    expect($post->title)->toBe('Updated title');
    expect($post->status)->toBe('published');
});

it('validates update data and maps errors back onto the fields', function () {
    $post = Post::factory()->create(['slug' => 'keep-me']);

    $this->postJson("/refilament/table/posts/record/{$post->id}", [
        'data' => [
            'title' => 'x',
            'slug' => 'invalid slug !',
            'author' => 'Ada',
            'status' => 'bogus',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'slug', 'status']);
});

it('ignores the record being edited in unique rules', function () {
    $post = Post::factory()->create(['slug' => 'unique-slug']);

    // Same slug, same record — must pass (the unique rule ignores the row).
    $this->postJson("/refilament/table/posts/record/{$post->id}", [
        'data' => ['title' => 'Fine', 'slug' => 'unique-slug', 'author' => 'Ada', 'status' => 'draft'],
    ])->assertOk();

    // A different record claiming the same slug must still fail.
    $other = Post::factory()->create(['slug' => 'other-slug']);

    $this->postJson("/refilament/table/posts/record/{$other->id}", [
        'data' => ['title' => 'Fine', 'slug' => 'unique-slug', 'author' => 'Ada', 'status' => 'draft'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('404s for unknown records and unknown tables on update', function () {
    $this->postJson('/refilament/table/posts/record/99999', ['data' => []])->assertNotFound();
    $this->postJson('/refilament/table/nope/record/1', ['data' => []])->assertNotFound();
});

it('404s for tables without a resource form (no update path)', function () {
    // A table registered outside discovery resolves, but the typed update
    // endpoint is a record-page feature — without a resource form schema
    // there are no authoritative rules, so there is no update path.
    app(Refilament::class)->registerTable('orphan', static fn (): Table => (new Table)->id('orphan')->query(Post::query()));

    $this->postJson('/refilament/table/orphan/record/1', ['data' => ['title' => 'x']])
        ->assertNotFound()
        ->assertJsonPath('error', 'Table has no form schema.');
});

it('reads the update success message from the form schema', function () {
    $schema = PostResource::form(new Schema);

    expect($schema->getUpdateSuccessMessage())->toBe('Post updated.');
});
