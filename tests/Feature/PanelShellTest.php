<?php

declare(strict_types=1);

it('shares the panel shell navigation on every Inertia page', function () {
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.refilament.panel.brandName', 'Refilament')
        ->assertJsonPath('props.refilament.panel.id', 'refilament')
        ->assertJsonPath('props.refilament.panel.sidebarCollapsible', false)
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
        ->assertJsonStructure([
            'props' => [
                'widgets' => [
                    '*' => ['type', 'stats'],
                ],
            ],
        ]);
});
