<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LogicException;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\CreateRecord;
use Refilament\Refilament\Resources\Pages\EditRecord;
use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Pages\ViewRecord;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Refilament\Resources\Pages\PostStats;
use Workbench\App\Refilament\Resources\PostResource;

it('declares the built-in pages by default and keeps custom slots', function () {
    $pages = PostResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'edit', 'view', 'stats']);
    expect($pages['index']->getPage())->toBe(ListRecords::class);
    expect($pages['create']->getPage())->toBe(CreateRecord::class);
    expect($pages['edit']->getPage())->toBe(EditRecord::class);
    expect($pages['view']->getPage())->toBe(ViewRecord::class);
    expect($pages['stats']->getPage())->toBe(PostStats::class);
});

it('serves the edit page pre-filled from the record', function () {
    $post = Post::factory()->create(['slug' => 'edit-me']);

    $this->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-edit')
        ->assertJsonPath('props.record', $post->id)
        ->assertJsonPath('props.resource', 'posts')
        ->assertJsonPath('props.resourceTitle', 'Post')
        ->assertJsonPath('props.id', 'post-form')
        ->assertJsonPath('props.data.title', $post->title)
        ->assertJsonPath('props.data.slug', 'edit-me');

    // The typed update endpoint (slice 1.7) is the page's save path — the
    // route must be registered and the record reachable through it.
    $this->postJson("/refilament/table/posts/record/{$post->id}", [
        'data' => ['title' => 'Renamed', 'slug' => 'edit-me', 'author' => 'Ada', 'status' => 'draft'],
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Post updated.')
        ->assertJsonPath('data.title', 'Renamed');

    expect($post->fresh()->title)->toBe('Renamed');
});

it('serves the view page with the record values', function () {
    $post = Post::factory()->create();

    $this->get("/refilament/posts/{$post->id}", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/resource-view')
        ->assertJsonPath('props.record', $post->id)
        ->assertJsonPath('props.resourceTitle', 'Post')
        // PostResource defines an infolist (slice 3.3), so the view page
        // drives through its read-only schema rather than the column list.
        ->assertJsonPath('props.schema.0.name', 'title')
        ->assertJsonPath('props.schema.0.value', $post->title);
});

it('404s for a missing record on record pages', function () {
    $this->get('/refilament/posts/999/edit', ['X-Inertia' => 'true'])->assertNotFound();
    $this->get('/refilament/posts/999', ['X-Inertia' => 'true'])->assertNotFound();
});

it('404s for non-numeric record segments', function () {
    $this->get('/refilament/posts/abc', ['X-Inertia' => 'true'])->assertNotFound();
    $this->get('/refilament/posts/abc/edit', ['X-Inertia' => 'true'])->assertNotFound();
});

it('serves a custom page from the getPages map', function () {
    Post::factory()->count(3)->create(['status' => 'published']);
    Post::factory()->count(2)->create(['status' => 'draft']);

    $this->get('/refilament/posts/stats', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'refilament/post-stats')
        ->assertJsonPath('props.resource', 'posts')
        ->assertJsonPath('props.resourceTitle', 'Post')
        ->assertJsonPath('props.stats.total', Post::count())
        ->assertJsonPath('props.stats.published', Post::where('status', 'published')->count())
        ->assertJsonPath('props.stats.draft', Post::where('status', 'draft')->count());
});

it('404s for unknown page names', function () {
    $this->get('/refilament/posts/bogus', ['X-Inertia' => 'true'])->assertNotFound();
});

it('throws when two resources register the same page name with different paths', function () {
    $refilament = app(Refilament::class);

    // A resource that claims the already-taken 'stats' name under a
    // different path — the shared route would silently shadow one of them.
    $class = new class extends \Refilament\Refilament\Resources\Resource
    {
        protected static ?string $model = Post::class;

        protected static ?string $tableId = 'conflicting';

        public static function table(Table $table): Table
        {
            return $table;
        }

        public static function form(Schema $schema): Schema
        {
            return $schema;
        }

        public static function getPages(): array
        {
            return [
                'stats' => PostStats::route('/other-stats'),
            ];
        }
    };

    $refilament->registerResources($class::class);

    expect(fn () => $refilament->registerPageRoutes())
        ->toThrow(LogicException::class, 'conflicting paths');
});

it('re-registers page routes for resources registered after boot', function () {
    $refilament = app(Refilament::class);

    $class = new class extends \Refilament\Refilament\Resources\Resource
    {
        protected static ?string $model = Post::class;

        protected static ?string $tableId = 'late';

        public static function table(Table $table): Table
        {
            return $table->id('late');
        }

        public static function form(Schema $schema): Schema
        {
            return $schema->id('late-form');
        }
    };

    $refilament->registerResources($class::class);
    $refilament->registerPageRoutes();

    expect(Route::has('refilament.resource.index'))->toBeTrue();
    expect($refilament->getResourceTableIds())->toContain('late');
});
