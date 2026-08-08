<?php

declare(strict_types=1);

it('serves the create post page as an Inertia page with the schema document', function () {
    $response = $this->get('/refilament/posts/create', ['X-Inertia' => 'true']);

    $response->assertOk();
    $response->assertJsonPath('component', 'refilament/resource-create');
    $response->assertJsonPath('props.id', 'post-form');
    $response->assertJsonPath('props.resource', 'posts');
    $response->assertJsonPath('props.resourceTitle', 'Post');
    $response->assertJsonPath('props.contract', 1);
    $response->assertJsonCount(1, 'props.schema');

    // Section > grid > title / slug / author, then the status select.
    $response->assertJsonPath('props.schema.0.type', 'section');
    $response->assertJsonPath('props.schema.0.heading', 'Details');
    $response->assertJsonPath('props.schema.0.schema.0.type', 'grid');
    $response->assertJsonCount(3, 'props.schema.0.schema.0.schema');

    $response->assertJsonPath('props.schema.0.schema.0.schema.0.name', 'title');
    $response->assertJsonPath('props.schema.0.schema.0.schema.0.required', true);
    $response->assertJsonPath('props.schema.0.schema.0.schema.0.validation', [
        'required', 'string', 'min:3', 'max:255',
    ]);

    $response->assertJsonPath('props.schema.0.schema.0.schema.1.name', 'slug');
    $response->assertJsonPath('props.schema.0.schema.0.schema.1.validation', [
        'required', 'string', 'regex:/^[a-z0-9-]+$/', 'max:255', 'unique:posts,slug',
    ]);

    // The user_id relationship select (slice C1) now sits between the grid
    // and the status select.
    $response->assertJsonPath('props.schema.0.schema.1.name', 'user_id');
    $response->assertJsonPath('props.schema.0.schema.1.searchable', true);
    $response->assertJsonPath('props.schema.0.schema.1.hint', 'Searchable user list');

    // Hint actions (slice C5) serialize on the author field with their
    // client-side visibility rule.
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.name', 'author');
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.hintActions.0.name', 'view-authors');
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.hintActions.0.url', '/refilament/users');
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.hintActions.0.openUrlInNewTab', true);
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.hintActions.0.visibleWhenFilled', ['author']);

    $response->assertJsonPath('props.schema.0.schema.2.name', 'status');
    $response->assertJsonPath('props.schema.0.schema.2.default', 'draft');
    $response->assertJsonCount(3, 'props.schema.0.schema.2.options');
    $response->assertJsonPath('props.schema.0.schema.2.hintIcon', [
        'icon' => 'chart-bar',
        'tooltip' => 'Shown as a badge in the listing',
    ]);

    // Initial data comes from the resource's formData() — the fields'
    // defaults (null when none is set).
    $response->assertJsonPath('props.data', [
        'title' => null,
        'slug' => null,
        'author' => null,
        'user_id' => null,
        'status' => 'draft',
        'created_at' => null,
    ]);
    $response->assertJsonPath('props.errors', []);
});
