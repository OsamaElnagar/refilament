<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

it('serves the create user page as an Inertia page with the schema document', function () {
    $response = $this->get('/refilament/users/create', ['X-Inertia' => 'true']);

    $response->assertOk();
    $response->assertJsonPath('component', 'refilament/resource-create');
    $response->assertJsonPath('props.id', 'user-form');
    $response->assertJsonPath('props.resource', 'users');
    $response->assertJsonPath('props.resourceTitle', 'User');
    $response->assertJsonPath('props.contract', 1);
    $response->assertJsonCount(3, 'props.schema');

    $response->assertJsonPath('props.schema.0.name', 'name');
    $response->assertJsonPath('props.schema.0.type', 'text_input');
    $response->assertJsonPath('props.schema.0.required', true);

    $response->assertJsonPath('props.schema.1.name', 'email');
    $response->assertJsonPath('props.schema.1.required', true);

    // The password column generates as a masked, revealable input — and the
    // auth-system remember_token column is skipped entirely.
    $response->assertJsonPath('props.schema.2.name', 'password');
    $response->assertJsonPath('props.schema.2.required', true);
    $response->assertJsonPath('props.schema.2.inputType', 'password');
    $response->assertJsonPath('props.schema.2.revealable', true);

    // Initial data comes from the resource's formData() — every field's
    // default is null here (none of the generated fields sets one).
    $response->assertJsonPath('props.data', [
        'name' => null,
        'email' => null,
        'password' => null,
    ]);
    $response->assertJsonPath('props.errors', []);
});

it('persists a user through the typed submit endpoint', function () {
    $this->postJson('/refilament/schema/user-form/submit', [
        'data' => [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ],
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User created.');

    $user = User::where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Ada Lovelace');
    expect(Hash::check('secret-password', $user->password))->toBeTrue();
});
