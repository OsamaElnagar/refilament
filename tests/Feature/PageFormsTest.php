<?php

declare(strict_types=1);

use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tests\Fixtures\FormPageResource;
use Refilament\Refilament\Tests\Fixtures\FormResourcePage;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Pages\SettingsPage;

it('serializes the page form payload for an authenticated visitor', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->actingAs($user)
        ->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.contract', 1)
        ->assertJsonPath('props.id', SettingsPage::getFormId())
        ->assertJsonPath('props.formTitle', 'Settings')
        ->assertJsonPath('props.formSubmitLabel', 'Save settings')
        ->assertJsonPath('props.hasUnsavedDataChangesAlert', true)
        // The record-bound starting values — the singular-resource idiom:
        // the page's getFormData() fills the form from the current user.
        ->assertJsonPath('props.data.name', 'Ada Lovelace')
        ->assertJsonPath('props.data.email', 'ada@example.com');
});

it('submits a page form through the typed submit endpoint and persists', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $this->actingAs($user)
        ->postJson('/refilament/schema/'.SettingsPage::getFormId().'/submit', [
            'data' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Settings saved.');

    expect($user->fresh())->name->toBe('Ada Lovelace');
    expect($user->fresh())->email->toBe('ada@example.com');
});

it('validates page form submissions server-side', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/refilament/schema/'.SettingsPage::getFormId().'/submit', [
            'data' => ['name' => '', 'email' => 'not-an-email'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The Name field is required.')
        ->assertJsonPath('errors.email.0', 'The Email field must be a valid email address.');

    // Nothing was persisted on a failed submission.
    expect($user->fresh())->name->toBe($user->name);
});

it('registers a custom resource page form and merges it into the payload', function () {
    $refilament = app(Refilament::class);
    $refilament->registerResources(FormPageResource::class);
    $refilament->registerPageRoutes();

    $this->get('/refilament/form-page/form', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-form')
        ->assertJsonPath('props.id', FormResourcePage::getFormId())
        ->assertJsonPath('props.formTitle', 'Form Resource Page')
        ->assertJsonPath('props.description', 'A custom resource page hosting a form.')
        ->assertJsonCount(1, 'props.schema')
        ->assertJsonPath('props.schema.0.name', 'title')
        ->assertJsonPath('props.data.title', null);

    // The resource page's form submits through the same typed endpoint.
    $this->postJson('/refilament/schema/'.FormResourcePage::getFormId().'/submit', [
        'data' => ['title' => 'Hello'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Form submitted.');
});

it('omits the form payload from pages that declare no form', function () {
    $this->get('/refilament/posts/stats', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.schema')
        ->assertJsonMissingPath('props.hasUnsavedDataChangesAlert');
});
