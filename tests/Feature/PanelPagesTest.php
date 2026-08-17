<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Refilament\Refilament\Http\Controllers\PanelPageController;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Pages\SettingsPage;

it('registers a standalone page route and serves its Inertia component', function () {
    // SettingsPage (workbench/app/Refilament/Pages) is discovered from the
    // configured folder and served at its slug, /refilament/settings.
    $this->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-form')
        ->assertJsonPath('props.formTitle', 'Settings')
        ->assertJsonPath('props.formSubmitLabel', 'Save settings')
        ->assertJsonPath('props.hasUnsavedDataChangesAlert', true)
        ->assertJsonPath('props.description', 'A page form — state is client-held, validated server-side on submit.');

    // The form payload rides on the page: the schema document (contract +
    // the name/email fields) and the record-bound starting values.
    $this->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.contract', 1)
        ->assertJsonPath('props.data', [])
        ->assertJsonPath('props.schema.0.type', 'section')
        ->assertJsonPath('props.schema.0.schema.0.type', 'grid')
        ->assertJsonCount(2, 'props.schema.0.schema.0.schema');
});

it('applies the panel auth gate to standalone pages', function () {
    // The gate reads the live panel per request — arming it on the resolved
    // panel instance is the consumer's toggle (config is seeded at boot).
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->loginUrl('/login');

    $this->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertRedirect('/login');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/page-form');
});

it('surfaces an opt-in standalone page in the sidebar contract', function () {
    $panel = $this->get('/refilament/settings', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    expect($panel['items'])
        ->toContainEqual([
            'key' => SettingsPage::class,
            'label' => 'Settings',
            'url' => '/refilament/settings',
            'icon' => 'heroicon-o-cog-6-tooth',
            'children' => [],
        ]);
});

it('resolves a standalone page from its slug', function () {
    $refilament = app(Refilament::class);

    expect($refilament->resolvePanelPage('settings'))->toBe(SettingsPage::class);
    expect($refilament->resolvePanelPage('does-not-exist'))->toBeNull();
});

it('turns a standalone page slug into the page class', function () {
    expect(SettingsPage::getSlug())->toBe('settings');
});

it('serves 404 for a slug that matches no standalone page', function () {
    $this->get('/refilament/does-not-exist', ['X-Inertia' => 'true'])->assertNotFound();
});

it('carries the web middleware group on the standalone page route', function () {
    // Regression: on Laravel 13 the RouteRegistrar's attribute() REPLACES
    // middleware, so a second ->middleware() chain would have dropped the
    // `web` group from the shared {page} route — and with it StartSession,
    // leaving the auth gate to reject every request (no session to read).
    // The standalone page route must carry the same middleware stack as the
    // dashboard and typed endpoints: `web` + the panel list + the gate.
    $request = Request::create('/refilament/settings', 'GET');
    $route = app('router')->getRoutes()->match($request);

    expect($route->getActionName())->toBe(PanelPageController::class.'@show')
        ->and($route->gatherMiddleware())->toContain('web')
        ->and($route->gatherMiddleware())->toContain(PanelAuthenticate::class);
});
