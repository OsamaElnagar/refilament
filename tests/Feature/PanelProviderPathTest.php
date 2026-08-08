<?php

declare(strict_types=1);

use Closure;
use Illuminate\Http\Request;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Refilament;

/**
 * Test-only middleware applied through the panel's ->middleware() list — it
 * stamps every panel route, proving consumer middleware is applied.
 */
class StampsPanelResponse
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        $response->headers->set('X-Panel-Test', 'applied');

        return $response;
    }
}

/**
 * Register the consumer-shaped panel (an `admin` URL prefix plus one
 * middleware entry) and (re)register the routes under it — the same calls the
 * package's booted() hook makes, but with the factory already in place. The
 * default /refilament routes registered at boot stay registered (the panel
 * cache was invalidated by the factory); the assertions target the
 * provider-path routes, which is the behavior under test.
 */
function registerAdminPanel(): void
{
    $refilament = app(Refilament::class);

    $refilament->registerPanel(
        static fn (Panel $panel): Panel => $panel
            ->path('admin')
            ->middleware([StampsPanelResponse::class]),
    );

    $refilament->registerRoutes();
    $refilament->registerPageRoutes();
}

it('registers every panel route under the provider-selected path', function () {
    registerAdminPanel();

    $this->get('/admin')->assertOk();
});

it('serves resource pages under the provider path', function () {
    registerAdminPanel();

    $this->get('/admin/posts')->assertOk();
});

it('serves the typed endpoints under the provider path', function () {
    registerAdminPanel();

    $this->get('/admin/table/posts?page=1')->assertOk();
});

it('applies panel middleware to the panel routes', function () {
    registerAdminPanel();

    $this->get('/admin')->assertHeader('X-Panel-Test', 'applied');
});

it('shares the panel path and derived dashboard URL in the shell props', function () {
    registerAdminPanel();

    $this->get('/admin', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.refilament.panel.path', 'admin')
        ->assertJsonPath('props.refilament.panel.dashboardUrl', '/admin');
});
