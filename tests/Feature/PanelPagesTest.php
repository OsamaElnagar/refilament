<?php

declare(strict_types=1);

use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Pages\SettingsPage;

it('registers a standalone page route and serves its Inertia component', function () {
    // SettingsPage (workbench/app/Refilament/Pages) is discovered from the
    // configured folder and served at its slug, /refilament/settings.
    $this->get('/refilament/settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/settings')
        ->assertJsonPath('props.environment', app()->environment());
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
        ->assertJsonPath('component', 'refilament/settings');
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
