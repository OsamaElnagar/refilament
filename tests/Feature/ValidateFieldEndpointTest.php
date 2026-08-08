<?php

declare(strict_types=1);

use Workbench\App\Models\Post;

it('accepts that an available unique value is valid', function () {
    Post::factory()->create(['slug' => 'taken']);

    $response = $this->postJson('/refilament/schema/post-form/validate', [
        'field' => 'slug',
        'value' => 'available-slug',
    ]);

    $response->assertOk();
    $response->assertJsonPath('valid', true);
});

it('reports a taken unique value as invalid with the field error', function () {
    Post::factory()->create(['slug' => 'taken']);

    $response = $this->postJson('/refilament/schema/post-form/validate', [
        'field' => 'slug',
        'value' => 'taken',
    ]);

    $response->assertOk();
    $response->assertJsonPath('valid', false);
    $response->assertJsonPath('errors.slug.0', 'The Slug has already been taken.');
});

it('validates only the unique rule live, ignoring other field rules', function () {
    // `slug` also carries `required` and `regex:/^[a-z0-9-]+$/` — none of
    // those are evaluated by the live endpoint, so a value that violates them
    // but is unique still passes. Those rules stay submit-time concerns.
    $response = $this->postJson('/refilament/schema/post-form/validate', [
        'field' => 'slug',
        'value' => 'Not A Slug (special chars!)',
    ]);

    $response->assertOk();
    $response->assertJsonPath('valid', true);
});

it('ignores the record being edited in the unique rule', function () {
    $existing = Post::factory()->create(['slug' => 'shared']);

    $response = $this->postJson('/refilament/schema/post-form/validate', [
        'field' => 'slug',
        'value' => 'shared',
        'record' => (string) $existing->getKey(),
    ]);

    $response->assertOk();
    $response->assertJsonPath('valid', true);
});

it('rejects an unknown schema', function () {
    $this->postJson('/refilament/schema/missing/validate', [
        'field' => 'slug',
        'value' => 'anything',
    ])->assertNotFound();
});

it('rejects an unknown field', function () {
    $this->postJson('/refilament/schema/post-form/validate', [
        'field' => 'nope',
        'value' => 'anything',
    ])->assertNotFound();
});

it('rejects a field with no unique rule', function () {
    // `status` has no unique rule, so it is not live-validated.
    $this->postJson('/refilament/schema/post-form/validate', [
        'field' => 'status',
        'value' => 'draft',
    ])->assertStatus(422);
});

it('validates the request payload', function () {
    $this->postJson('/refilament/schema/post-form/validate', [
        'value' => 'missing-field',
    ])->assertStatus(422);
});
