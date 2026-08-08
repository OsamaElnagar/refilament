<?php

declare(strict_types=1);

use Workbench\App\Models\Post;

it('serves the posts page as an Inertia page with the initial payload', function () {
    Post::factory()->count(45)->create();

    $response = $this->get('/refilament/posts', ['X-Inertia' => 'true']);

    $response->assertOk();
    $response->assertJsonPath('component', 'refilament/resource-table');
    $response->assertJsonPath('props.id', 'posts');
    $response->assertJsonPath('props.heading', 'Posts');
    $response->assertJsonPath('props.resourceTitle', 'Post');
    $response->assertJsonCount(7, 'props.columns');
    $response->assertJsonCount(10, 'props.rows');
    $response->assertJsonPath('props.page', 1);
    $response->assertJsonPath('props.perPage', 10);
    $response->assertJsonPath('props.total', 45);
    $response->assertJsonPath('props.lastPage', 5);
    $response->assertJsonPath('props.columns.1.sortable', true);
    $response->assertJsonPath('props.columns.1.searchable', true);
    $response->assertJsonPath('props.columns.2.name', 'author');
    $response->assertJsonMissingPath('props.columns.2.sortable');

    // Toggleable columns: author and user carry the flag, id never does.
    $response->assertJsonPath('props.columns.2.toggleable', true);
    $response->assertJsonPath('props.columns.4.name', 'user.name');
    $response->assertJsonPath('props.columns.4.toggleable', true);
    $response->assertJsonMissingPath('props.columns.0.toggleable');
    // The user.name column resolves through the dot-notation relationship
    // resolver against the eager-loaded relation — the cell is the related
    // name (a literal "user.name" key, asserted via array access since both
    // assertJsonPath and collect()->pluck treat dots as path traversal).
    collect($response->json('props.rows'))->each(
        static function (array $row): void {
            expect($row['user.name'])->toBeString()->not->toBeEmpty();
        },
    );
    $response->assertJsonPath('props.filters.0.name', 'status');
    $response->assertJsonCount(3, 'props.filters.0.options');
    // edit (slice 1.2) precedes publish and delete on every row.
    $response->assertJsonCount(3, 'props.actions');
    $response->assertJsonPath('props.actions.0.name', 'edit');
    $response->assertJsonPath('props.actions.0.type', 'edit');
    $response->assertJsonPath('props.actions.0.schema', 'post-form');
    $response->assertJsonPath('props.actions.1.name', 'publish');
    $response->assertJsonPath('props.actions.2.requiresConfirmation', true);
    $response->assertJsonStructure([
        'props' => [
            'rows' => [
                '*' => ['id', 'actions'],
            ],
        ],
    ]);
});
