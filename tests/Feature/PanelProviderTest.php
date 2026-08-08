<?php

declare(strict_types=1);

use LogicException;
use Refilament\Refilament\Http\Middleware\Authenticate;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Refilament;

it('builds the default panel from config when no provider registers', function () {
    $panel = app(Refilament::class)->panel();

    expect($panel->getId())->toBe('refilament');
    expect($panel->getPath())->toBe('refilament');
    expect($panel->getDashboardUrl())->toBe('/refilament');
    expect($panel->getResources())->not->toBeEmpty();
});

it('applies the registered panel factory on top of the config-seeded panel', function () {
    app(Refilament::class)->registerPanel(
        static fn (Panel $panel): Panel => $panel->path('admin')->brandName('Admin Panel'),
    );

    $panel = app(Refilament::class)->panel();

    expect($panel->getPath())->toBe('admin');
    expect($panel->getBrandName())->toBe('Admin Panel');
    expect($panel->getDashboardUrl())->toBe('/admin');
    // The config-seeded surface survives the override — the discovered
    // resources and the config pages are still present.
    expect($panel->getResources())->not->toBeEmpty();
});

it('invalidates an early-built panel when a factory registers', function () {
    // The workbench builds + caches the config panel during boot; a consumer
    // provider registering later must win.
    expect(app(Refilament::class)->panel()->getPath())->toBe('refilament');

    app(Refilament::class)->registerPanel(
        static fn (Panel $panel): Panel => $panel->path('admin'),
    );

    expect(app(Refilament::class)->panel()->getPath())->toBe('admin');
});

it('derives the dashboard URL from the panel path', function () {
    app(Refilament::class)->registerPanel(
        static fn (Panel $panel): Panel => $panel->path('admin'),
    );

    expect(app(Refilament::class)->panel()->getDashboardUrl())->toBe('/admin');
});

it('rejects a second panel registration', function () {
    $refilament = app(Refilament::class);

    $refilament->registerPanel(static fn (Panel $panel): Panel => $panel);
    $refilament->registerPanel(static fn (Panel $panel): Panel => $panel);
})->throws(LogicException::class);

it('gates shell pages when the live panel enlists the auth middleware', function () {
    app(Refilament::class)->registerPanel(
        static fn (Panel $panel): Panel => $panel
            ->authMiddleware([Authenticate::class])
            ->loginUrl('/login'),
    );

    // The gate reads the *live* panel per request — no route re-registration
    // needed after a consumer toggles ->authMiddleware().
    $this->get('/refilament')->assertRedirect('/login');
});

it('keeps shell pages open when the auth middleware is not enlisted', function () {
    $this->get('/refilament')->assertOk();
});

it('installs the panel provider and publishes the package assets', function () {
    // The testbench skeleton is the app in tests, so the command's artifacts
    // land inside vendor/orchestra/testbench-core/laravel — restore the
    // skeleton afterwards so no later test boots with the generated provider
    // registered (a fresh app per test reads bootstrap/providers.php fresh).
    $providersFile = base_path('bootstrap/providers.php');
    $providersBackup = file_exists($providersFile) ? file_get_contents($providersFile) : null;
    $providerPath = app_path('Providers/RefilamentPanelProvider.php');

    try {
        $this->artisan('refilament:install')->assertSuccessful();

        // Panel provider generated from the stub, consumer-owned.
        expect(file_exists($providerPath))->toBeTrue();

        $contents = file_get_contents($providerPath);

        expect($contents)->toContain('class RefilamentPanelProvider extends PanelProvider');
        expect($contents)->toContain("->path('refilament')");

        // Auto-registered in bootstrap/providers.php.
        expect(file_get_contents($providersFile))->toContain('App\\Providers\\RefilamentPanelProvider::class');
    } finally {
        // Leave the skeleton pristine — these are throwaway artifacts.
        if ($providersBackup !== null) {
            file_put_contents($providersFile, $providersBackup);
        }

        @unlink($providerPath);
        @unlink(config_path('refilament.php'));
        @unlink(database_path('migrations/2026_01_01_000001_create_refilament_notifications_table.php'));
        app('files')->deleteDirectory(base_path('public/vendor/refilament'));
    }
});
