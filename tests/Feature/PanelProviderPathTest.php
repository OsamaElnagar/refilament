<?php

declare(strict_types=1);

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteModel;
use Illuminate\Support\Facades\Route;
use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\Post;

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
            ->middleware([StampsPanelResponse::class])
            // A declared navigation group with a member — so the shell-payload
            // test below exercises the grouped-items URL sweep, not just the
            // ungrouped items.
            ->navigationGroups([NavigationGroup::make('Commerce')->collapsible()])
            ->navigationItems([
                NavigationItem::make('Commerce dashboard')
                    ->key('commerce-dashboard')
                    ->url('/admin/commerce')
                    ->group('Commerce'),
            ]),
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

it('serializes every shell navigation URL under the provider path', function () {
    registerAdminPanel();

    // The React shell reads props.refilament.panel.path and builds every
    // fetch/visit URL from it (lib/panel.ts panelUrl()); the server must
    // serialize the nav under the same prefix — a stale /refilament/ link
    // anywhere in the payload is the regression this locks.
    /** @var array<string, mixed> $panel */
    $panel = $this->get('/admin', ['X-Inertia' => 'true'])
        ->assertOk()
        ->json('props.refilament.panel');

    expect($panel['path'])->toBe('admin');
    expect($panel['dashboardUrl'])->toBe('/admin');

    // Every sidebar item (resource pages + opt-in custom pages) links under
    // the provider path — never the package default.
    /** @var array<int, array{url: string}> $items */
    $items = $panel['items'];

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item['url'])->toStartWith('/admin/');
    }

    // Grouped members (when a consumer declares navigation groups) follow
    // the same rule.
    /** @var array<int, array{items: array<int, array{url: string}>}> $groups */
    $groups = $panel['groups'];

    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            expect($item['url'])->toStartWith('/admin/');
        }
    }
});

it('resolves the modal-edit submit URL under the provider path', function () {
    registerAdminPanel();

    // The React action modal POSTs edits to `/table/{table}/action/{action}`
    // under the panel path (panelUrl in resources/js/lib/panel.ts) — the
    // submit endpoint must be registered under the provider-selected prefix,
    // or the modal 404s. This locks the regression: the submit URL used to
    // fall back to a hardcoded /refilament/ prefix regardless of ->path().
    // The route name pins to the *first* registration (Laravel's nameList
    // keeps the earliest route with a name), so assert the collection holds
    // an admin-prefixed instance of the endpoint rather than getByName().
    $tableActionRoute = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RouteModel $route): bool => $route->getName() === 'refilament.table.action'
            && $route->uri() === 'admin/table/{table}/action/{action}');

    expect($tableActionRoute)->not->toBeNull();

    // And the endpoint actually resolves there — a real edit round-trip
    // through the admin-prefixed URL the client would POST to.
    $post = Post::factory()->create(['slug' => fake()->unique()->slug()]);

    $this->postJson('/admin/table/posts/action/edit', [
        'record' => $post->id,
        'data' => [
            'title' => 'Edited under admin',
            'slug' => $post->slug,
            'author' => $post->author,
            'status' => $post->status,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($post->refresh()->title)->toBe('Edited under admin');
});
