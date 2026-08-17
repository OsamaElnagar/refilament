<?php

declare(strict_types=1);

// The expected version is the xxh128 of the workbench's compiled-assets
// manifest — the same file `AppendInertiaVersion` (and Inertia's own
// `Middleware::version()`) hashes. The workbench ships a built manifest, but
// if it is ever cleaned these assertions would fail confusingly, so skip
// instead when there is nothing to hash.
beforeEach(function () {
    if (! file_exists(public_path('build/manifest.json'))) {
        $this->markTestSkipped('No compiled-assets manifest — cannot assert the version header.');
    }
});

it('appends the inertia version header to the dashboard response', function () {
    $version = hash_file('xxh128', public_path('build/manifest.json'));

    // The shell's Inertia fetch (X-Inertia) — the version handshake the
    // client compares against its own compiled assets.
    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertHeader('X-Inertia-Version', $version);
});

it('appends the version header to resource pages and the typed endpoints', function () {
    $version = hash_file('xxh128', public_path('build/manifest.json'));

    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertHeader('X-Inertia-Version', $version);

    $this->getJson('/refilament/table/posts')
        ->assertOk()
        ->assertHeader('X-Inertia-Version', $version);
});
