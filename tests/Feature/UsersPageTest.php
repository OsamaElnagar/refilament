<?php

declare(strict_types=1);

use Workbench\App\Models\User;

it('serves the users page as an Inertia page with the initial payload', function () {
    User::factory()->count(45)->create();

    $response = $this->get('/refilament/users', ['X-Inertia' => 'true']);

    $response->assertOk();
    $response->assertJsonPath('component', 'refilament/resource-table');
    $response->assertJsonPath('props.id', 'users');
    $response->assertJsonPath('props.resourceTitle', 'User');
    $response->assertJsonCount(5, 'props.columns');
    $response->assertJsonCount(10, 'props.rows');
    $response->assertJsonPath('props.page', 1);
    $response->assertJsonPath('props.perPage', 10);
    $response->assertJsonPath('props.total', 45);
    $response->assertJsonPath('props.lastPage', 5);
    $response->assertJsonPath('props.columns.0.name', 'id');
    $response->assertJsonPath('props.columns.0.sortable', true);
    $response->assertJsonPath('props.columns.1.name', 'name');
    $response->assertJsonMissingPath('props.columns.1.sortable');
    $response->assertJsonPath('props.columns.2.name', 'email');
    $response->assertJsonPath('props.columns.3.name', 'email_verified_at');
    $response->assertJsonPath('props.columns.3.sortable', true);
    $response->assertJsonPath('props.columns.4.name', 'password');

    // Row values serialize through the attribute path, including the column
    // that is #[Hidden] on the model (password) — the serializer must not
    // fall back to toArray(), which would drop it.
    $response->assertJsonPath('props.rows.0.name', fn (mixed $value): bool => is_string($value) && $value !== '');
    $response->assertJsonPath('props.rows.0.email', fn (mixed $value): bool => is_string($value) && $value !== '');
    $response->assertJsonPath('props.rows.0.password', fn (mixed $value): bool => is_string($value) && $value !== '');
    $response->assertJsonMissingPath('props.filters');

    // The modal create header action (slice 1.1) and the confirmed row
    // delete (slice 1.2).
    $response->assertJsonPath('props.headerActions.0.name', 'create');
    $response->assertJsonPath('props.actions', [
        [
            'name' => 'delete',
            'label' => 'Delete',
            'color' => 'danger',
            'requiresConfirmation' => true,
        ],
    ]);
    $response->assertJsonMissingPath('props.heading');
});
