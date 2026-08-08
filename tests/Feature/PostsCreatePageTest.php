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

    $response->assertJsonPath('props.schema.0.schema.1.name', 'status');
    $response->assertJsonPath('props.schema.0.schema.1.default', 'draft');
    $response->assertJsonCount(3, 'props.schema.0.schema.1.options');

    // Initial data comes from the resource's formData() — the fields'
    // defaults (null when none is set).
    $response->assertJsonPath('props.data', [
        'title' => null,
        'slug' => null,
        'author' => null,
        'status' => 'draft',
    ]);
    $response->assertJsonPath('props.errors', []);
});
