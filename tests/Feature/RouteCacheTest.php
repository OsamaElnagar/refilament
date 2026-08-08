<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps the generated page routes cacheable', function () {
    File::ensureDirectoryExists(base_path('bootstrap/cache'));

    $cached = base_path('bootstrap/cache/routes-v7.php');

    @unlink($cached);

    // Sanity: the page routes serve before caching.
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])->assertOk();

    try {
        // Serializing the whole route collection must succeed — the page
        // routes are controller actions with where() constraints baked at
        // boot, and the workbench routes are closure-free, so route:cache
        // stays green (docs/ROADMAP.md "1.6 Page system").
        $this->artisan('route:cache')->assertExitCode(0);

        expect(file_exists($cached))->toBeTrue();

        // The cached collection carries the generated page routes.
        $serialized = file_get_contents($cached);

        expect($serialized)->toContain('refilament.resource.index');
        expect($serialized)->toContain('refilament.resource.stats');
        expect($serialized)->toContain('ResourcePageController');

        // Note: no requests are made after route:cache — the command boots a
        // fresh app whose in-memory sqlite is empty, so further assertions
        // would test the wrong container.
    } finally {
        $this->artisan('route:clear');
        @unlink($cached);
    }
});
