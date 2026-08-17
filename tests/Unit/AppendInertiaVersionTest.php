<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Support\Header;
use Refilament\Refilament\Http\Middleware\AppendInertiaVersion;

it('resolves the version from the compiled-assets manifest when none is registered', function () {
    $manifest = public_path('build/manifest.json');

    if (! file_exists($manifest)) {
        $this->markTestSkipped('No compiled-assets manifest — cannot assert the fallback hash.');
    }

    $response = (new AppendInertiaVersion)->handle(
        Request::create('/admin'),
        static fn () => new JsonResponse(['ok' => true]),
    );

    expect($response->headers->get(Header::VERSION))
        ->toBe(hash_file('xxh128', $manifest));
});

it('uses a globally-registered inertia version when one is set', function () {
    Inertia::version('from-global');

    $response = (new AppendInertiaVersion)->handle(
        Request::create('/admin'),
        static fn () => new JsonResponse(['ok' => true]),
    );

    expect($response->headers->get(Header::VERSION))->toBe('from-global');
});

it('hashes the asset url when the app defines one', function () {
    config(['app.asset_url' => 'https://cdn.example.com']);

    $response = (new AppendInertiaVersion)->handle(
        Request::create('/admin'),
        static fn () => new JsonResponse(['ok' => true]),
    );

    expect($response->headers->get(Header::VERSION))
        ->toBe(hash('xxh128', 'https://cdn.example.com'));
});

it('leaves an existing version header untouched', function () {
    // A consumer's own HandleInertiaRequests already ran (it runs ahead of
    // the panel chain when appended to web) — its value stays authoritative.
    $response = (new AppendInertiaVersion)->handle(
        Request::create('/admin'),
        static fn () => new JsonResponse(['ok' => true], 200, [Header::VERSION => 'consumer-set']),
    );

    expect($response->headers->get(Header::VERSION))->toBe('consumer-set');
});
