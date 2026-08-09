<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

it('registers panel routes inside the web middleware group', function () {
    // The dashboard, a POST endpoint, and a notification endpoint must all
    // carry the framework's `web` group (sessions + CSRF + SubstituteBindings)
    // — mirroring Filament's `->hasRoutes('web')`.
    foreach ([
        'refilament.dashboard',
        'refilament.schema.submit',
        'refilament.table.action',
        'refilament.notifications.read',
    ] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('web');
    }
});

it('runs panel POSTs through a real session and sets the XSRF-TOKEN cookie', function () {
    $this->withSession([]);

    $response = $this->postJson('/refilament/schema/post-form/submit', [
        'data' => [
            'title' => 'Session post',
            'slug' => 'session-post',
            'status' => 'published',
            'author' => 'Ada Lovelace',
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    // The CSRF middleware sets the XSRF-TOKEN cookie on the response — the
    // cookie the shell reads back and sends as X-XSRF-TOKEN on later POSTs.
    // (getName() rather than pluck('name'): Symfony's Cookie properties are
    // private, so the collection's pluck can't read them.)
    $cookies = collect($response->headers->getCookies())->map(fn ($cookie) => $cookie->getName());

    expect($cookies)->toContain('XSRF-TOKEN');
});

it('applies the framework CSRF middleware to panel POST routes', function () {
    // The `web` group on a panel POST route must resolve to include the
    // framework's CSRF middleware (PreventRequestForgery) — the middleware
    // that rejects a POST whose token doesn't match the session's. Under
    // env=testing the middleware skips the check (runningUnitTests() reads
    // the env binding), so this asserts the wiring: a tokenless POST from
    // outside the shell is stopped by the very middleware a browser POST
    // validates against. (Flipping the app env to force a live 419 trips
    // Laravel 12's production-only auto-install prompts under testbench's
    // mocked console output, so the rejection is asserted structurally here
    // and the happy path — real session token → accepted — runs in the test
    // above and across the whole POST suite.)
    // The kernel's own groups are populated by testbench's after_resolving
    // hook; resolving it here ensures they're set before the assertion.
    $groups = $this->app->make(Kernel::class)->getMiddlewareGroups();

    $route = Route::getRoutes()->getByName('refilament.schema.submit');

    $resolved = [];

    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && isset($groups[$middleware])) {
            $resolved = [...$resolved, ...$groups[$middleware]];
        } else {
            $resolved[] = $middleware;
        }
    }

    expect($resolved)->toContain(PreventRequestForgery::class);
});
