<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;

it('auto-registers one shared page route per getPages() slot', function () {
    // Page names are registered once — every resource shares the built-in
    // URI shapes, and the route collection is keyed by method|uri, so the
    // first resource to declare a page name wins its path.
    expect(Route::has('refilament.resource.index'))->toBeTrue();
    expect(Route::has('refilament.resource.create'))->toBeTrue();
    expect(Route::has('refilament.resource.edit'))->toBeTrue();
    expect(Route::has('refilament.resource.view'))->toBeTrue();
    expect(Route::has('refilament.resource.stats'))->toBeTrue();

    expect(Route::getRoutes()->getByName('refilament.resource.index')?->uri())->toBe('refilament/{resource}');
    expect(Route::getRoutes()->getByName('refilament.resource.create')?->uri())->toBe('refilament/{resource}/create');
    expect(Route::getRoutes()->getByName('refilament.resource.edit')?->uri())->toBe('refilament/{resource}/{record}/edit');
    expect(Route::getRoutes()->getByName('refilament.resource.view')?->uri())->toBe('refilament/{resource}/{record}');
    expect(Route::getRoutes()->getByName('refilament.resource.stats')?->uri())->toBe('refilament/{resource}/stats');
});

it('constrains the record segment to integers', function () {
    $route = Route::getRoutes()->getByName('refilament.resource.view');

    expect($route)->not->toBeNull();
    expect($route->wheres['record'] ?? null)->toBe('[0-9]+');
});

it('serves the generic list page component for a discovered resource', function () {
    Post::factory()->count(3)->create();

    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-table')
        ->assertJsonPath('props.id', 'posts')
        ->assertJsonPath('props.heading', 'Posts')
        ->assertJsonPath('props.resourceTitle', 'Post');
});

it('404s for ids with no registered resource', function () {
    $this->get('/refilament/nope', ['X-Inertia' => 'true'])->assertNotFound();
});

it('does not serve pages for tables registered outside discovery', function () {
    app(Refilament::class)->registerTable('widget', static fn (): Table => (new Table)->id('widget'));

    // The tables resolve, but the page routes only match ids discovered at
    // boot — the constraint is what gates which URLs reach them.
    $this->get('/refilament/widget', ['X-Inertia' => 'true'])->assertNotFound();
    $this->get('/refilament/widget/create', ['X-Inertia' => 'true'])->assertNotFound();
});

it('serves every discovered resource through the shared page routes', function () {
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.resource', 'posts');

    $this->get('/refilament/users', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.resource', 'users');
});

it('serves the generic create page for a discovered resource', function () {
    $this->get('/refilament/posts/create', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-create')
        ->assertJsonPath('props.resource', 'posts')
        ->assertJsonPath('props.resourceTitle', 'Post')
        ->assertJsonPath('props.id', 'post-form');
});

it('404s for create routes of unknown resources', function () {
    $this->get('/refilament/nope/create', ['X-Inertia' => 'true'])->assertNotFound();
});

it('does not shadow the typed table endpoint or app-owned pages', function () {
    Post::factory()->count(2)->create();

    // The typed JSON endpoint must still win over the page routes.
    $this->getJson('/refilament/table/posts')
        ->assertOk()
        ->assertJsonPath('id', 'posts');

    // App-specific pages that are not resource tables stay untouched — they
    // still resolve to their own component rather than a resource page.
    $this->get('/refilament/playground', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/playground');
});
