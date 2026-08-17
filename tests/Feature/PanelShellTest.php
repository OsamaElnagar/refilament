<?php

declare(strict_types=1);

it('shares the panel shell navigation on every Inertia page', function () {
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.refilament.panel.brandName', 'Refilament')
        ->assertJsonPath('props.refilament.panel.id', 'refilament')
        ->assertJsonPath('props.refilament.panel.sidebarCollapsible', false)
        ->assertJsonPath('props.refilament.panel.renderHooks', ['panels::sidebar.footer' => '<div class="flex items-center justify-between gap-2 px-2 py-1 text-xs text-muted-foreground"><span>Refilament v0</span></div>'])
        ->assertJsonPath('props.refilament.panel.notifications.polling', '10s')
        ->assertJsonStructure([
            'props' => [
                'refilament' => [
                    'panel' => [
                        'groups' => ['*' => ['label', 'items']],
                        'items' => ['*' => ['key', 'label', 'url', 'children']],
                    ],
                ],
            ],
        ]);
});

it('includes one sidebar item per discovered resource', function () {
    $panel = $this->get('/refilament/posts', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    $labels = collect($panel['items'])->pluck('label')->all();

    expect($labels)->toContain('Posts');
});

it('surfaces an opt-in custom resource page in the sidebar', function () {
    $panel = $this->get('/refilament/posts', ['X-Inertia' => 'true'])->json('props.refilament.panel');

    $stats = collect($panel['items'])->firstWhere('label', 'Stats');

    expect($stats['url'])->toBe('/refilament/posts/stats');
    expect($stats['icon'])->toBe('chart-bar');
});

it('shares the panel colors in the shell contract', function () {
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.refilament.panel.colors.primary', '#6366f1');
});

it('serves the dashboard with the registered widgets', function () {
    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/dashboard')
        ->assertJsonPath('props.refilament.panel.brandName', 'Refilament')
        ->assertJsonPath('props.widgets.0.type', 'stats_overview')
        ->assertJsonPath('props.widgets.1.type', 'table')
        ->assertJsonPath('props.widgets.1.table.id', 'recent-posts-table')
        ->assertJsonStructure([
            'props' => [
                'widgets' => [
                    '*' => ['type'],
                ],
            ],
        ]);
});

it('renders refilament pages through the package root view', function () {
    // A non-X-Inertia request renders the root view. The package pages must
    // render through refilament::app (v1 shipping — docs/ARCHITECTURE.md
    // "Frontend delivery"), which in the workbench (no published assets)
    // falls back to @vite; in a consumer it loads the prebuilt bundle.
    $html = $this->get('/refilament')->assertOk()->getContent();

    expect($html)
        ->toContain('<div id="app"')
        ->toContain('<title>');

    // The workbench has no published vendor/refilament bundle, so the view
    // must have taken the @vite fallback — never reference the prebuilt.
    expect($html)->not->toContain('vendor/refilament/refilament.js');
});

it('renders the prebuilt bundle when the consumer assets are published', function () {
    // Simulate a consumer: drop the bundle where vendor:publish puts it, so
    // the root view takes the published-assets branch. In the workbench the
    // public dir is workbench/public, so that is where we plant it.
    $publicDir = public_path('vendor/refilament');

    File::ensureDirectoryExists($publicDir);
    File::put($publicDir.'/refilament.js', 'console.log("stub");');
    File::put($publicDir.'/refilament.css', '/* stub */');

    try {
        $html = $this->get('/refilament')->assertOk()->getContent();

        // asset() emits an absolute URL in tests (http://localhost/...) —
        // match the path, not the host. Both URLs carry the mtime cache-bust
        // (?v=) the published-assets branch appends so a republished bundle
        // forces a browser refetch.
        expect($html)
            ->toContain('/vendor/refilament/refilament.js?v=')
            ->toContain('/vendor/refilament/refilament.css?v=')
            ->not->toContain('/build/assets/');
    } finally {
        File::deleteDirectory($publicDir);
    }
});
