<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class CanEditOnlyPostsPolicy
{
    public function view(?User $user, Post $post): bool
    {
        return false;
    }

    public function update(?User $user, Post $post): bool
    {
        return true;
    }
}

class CannotActOnPostsPolicy
{
    public function view(?User $user, Post $post): bool
    {
        return false;
    }

    public function update(?User $user, Post $post): bool
    {
        return false;
    }
}

class NoResourceCrumbListPage extends ListRecords
{
    /**
     * The page-level opt-out (slice 1.11) — the page decides whether its
     * breadcrumbs start with the resource crumb, mirroring Filament's
     * `Resources\Pages\Page::hasResourceBreadcrumbs()`.
     */
    public static function hasResourceBreadcrumbs(): bool
    {
        return false;
    }
}

class NoResourceCrumbResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'dashboardish';

    protected static ?string $formId = 'dashboardish-form';

    public static function table(Table $table): Table
    {
        return $table->id('dashboardish')->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('dashboardish-form');
    }

    public static function getPages(): array
    {
        return [
            'index' => NoResourceCrumbListPage::route('/'),
        ];
    }
}

class BrandedResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'branded';

    protected static ?string $formId = 'branded-form';

    protected static ?string $breadcrumb = 'Blog posts';

    public static function table(Table $table): Table
    {
        return $table->id('branded')->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('branded-form');
    }
}

class BreadcrumbedSettingsPage extends Page
{
    public static function getInertiaComponent(): string
    {
        return 'refilament/dashboard';
    }

    public static function getBreadcrumbs(): array
    {
        return [
            ['label' => 'Home', 'url' => '/refilament'],
            ['label' => 'Settings'],
        ];
    }
}

class RecordStatementPage extends \Refilament\Refilament\Resources\Pages\Page
{
    /**
     * A custom record page that renders without the built-in view/edit gates
     * — the fixture through which the plain record crumb (no view/edit
     * ability) is reachable, since the built-in edit/view pages authorize
     * first.
     */
    public static function getInertiaComponent(): string
    {
        return 'refilament/resource-view';
    }

    public static function getViewData(string $resource): array
    {
        return [];
    }
}

class RecordStatementResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $tableId = 'statements';

    protected static ?string $formId = 'statements-form';

    // The record crumb needs a record title (slice 1.11) — the attribute
    // that names a record, mirroring PostResource's demo fixture.
    protected static ?string $recordTitleAttribute = 'title';

    public static function table(Table $table): Table
    {
        return $table->id('statements')->query(Post::query());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->id('statements-form');
    }

    public static function getPages(): array
    {
        return [
            ...parent::getPages(),
            'statement' => RecordStatementPage::route('/{record}/statement'),
        ];
    }
}

it('serves the list page breadcrumbs: resource crumb linked to the list, then the page crumb', function () {
    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(2, 'props.breadcrumbs')
        // The resource crumb (slice 1.11): the resource's breadcrumb label
        // ("Posts"), linking to the list page — Filament's
        // getResourceBreadcrumbs().
        ->assertJsonPath('props.breadcrumbs.0.label', 'Posts')
        ->assertJsonPath(
            'props.breadcrumbs.0.url',
            route('refilament.resource.index', ['resource' => 'posts']),
        )
        // The page crumb: the built-in list page's "List" (Filament's
        // list-records breadcrumb default), never a link (the current page).
        ->assertJsonPath('props.breadcrumbs.1.label', 'List')
        ->assertJsonMissingPath('props.breadcrumbs.1.url');
});

it('serves the create page breadcrumbs: resource crumb then Create', function () {
    $this->get('/refilament/posts/create', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(2, 'props.breadcrumbs')
        ->assertJsonPath('props.breadcrumbs.0.label', 'Posts')
        ->assertJsonPath('props.breadcrumbs.1.label', 'Create')
        ->assertJsonMissingPath('props.breadcrumbs.1.url');
});

it('serves the edit page breadcrumbs with the record crumb linked to the view page', function () {
    $post = Post::factory()->create();

    $this->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(3, 'props.breadcrumbs')
        ->assertJsonPath('props.breadcrumbs.0.label', 'Posts')
        // The record crumb (slice 1.11): the record's title, linked to the
        // view page — Filament's InteractsWithRecord::getBreadcrumbs(): the
        // user can view the record, so the view page URL wins.
        ->assertJsonPath('props.breadcrumbs.1.label', $post->title)
        ->assertJsonPath(
            'props.breadcrumbs.1.url',
            route('refilament.resource.view', ['resource' => 'posts', 'record' => $post->id]),
        )
        ->assertJsonPath('props.breadcrumbs.2.label', 'Edit')
        ->assertJsonMissingPath('props.breadcrumbs.2.url');
});

it('serves the view page breadcrumbs with the record crumb linked to the view page', function () {
    $post = Post::factory()->create();

    $this->get("/refilament/posts/{$post->id}", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(3, 'props.breadcrumbs')
        ->assertJsonPath('props.breadcrumbs.0.label', 'Posts')
        ->assertJsonPath('props.breadcrumbs.1.label', $post->title)
        ->assertJsonPath('props.breadcrumbs.2.label', 'View')
        ->assertJsonMissingPath('props.breadcrumbs.2.url');
});

it('links the record crumb to the edit page when the user cannot view but can edit', function () {
    $post = Post::factory()->create();

    // A policy that denies `view` but allows `update` — the record crumb
    // must fall back to the edit-page link (Filament's elseif branch).
    Gate::policy(Post::class, CanEditOnlyPostsPolicy::class);

    try {
        $this->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.breadcrumbs.1.label', $post->title)
            ->assertJsonPath(
                'props.breadcrumbs.1.url',
                route('refilament.resource.edit', ['resource' => 'posts', 'record' => $post->id]),
            );
    } finally {
        Gate::policy(Post::class, null);
    }
});

it('shows the record crumb plain when the user can neither view nor edit', function () {
    $post = Post::factory()->create();

    // A custom record page renders without the built-in view/edit gates, so
    // the plain record crumb is reachable here (on the built-in edit/view
    // pages the page's own authorization would 403 first).
    $refilament = app(Refilament::class);
    $refilament->registerResources(RecordStatementResource::class);
    $refilament->registerPageRoutes();

    Gate::policy(Post::class, CannotActOnPostsPolicy::class);

    try {
        $this->get("/refilament/statements/{$post->id}/statement", ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(3, 'props.breadcrumbs')
            ->assertJsonPath('props.breadcrumbs.0.label', 'Posts')
            ->assertJsonPath('props.breadcrumbs.1.label', $post->title)
            // Plain — no url key at all (never linking to a page the user
            // can't act on).
            ->assertJsonMissingPath('props.breadcrumbs.1.url')
            // The page crumb defaults to the page's title — here the
            // headlined class name (no navigation label was set).
            ->assertJsonPath('props.breadcrumbs.2.label', 'Record Statement Page');
    } finally {
        Gate::policy(Post::class, null);
    }
});

it('serves the custom page breadcrumbs: resource crumb then the page title', function () {
    $this->get('/refilament/posts/stats', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(2, 'props.breadcrumbs')
        ->assertJsonPath('props.breadcrumbs.0.label', 'Posts')
        // A custom page's crumb is its title (here the navigation label
        // "Stats" — Filament's `$breadcrumb ?? getTitle()` default).
        ->assertJsonPath('props.breadcrumbs.1.label', 'Stats')
        ->assertJsonMissingPath('props.breadcrumbs.1.url');
});

it('omits the breadcrumbs key entirely when the panel toggle is off', function () {
    app(Refilament::class)->panel()->breadcrumbs(false);

    try {
        $this->get('/refilament/posts', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonMissingPath('props.breadcrumbs');

        // The gate applies to record pages too.
        $post = Post::factory()->create();

        $this->get("/refilament/posts/{$post->id}/edit", ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonMissingPath('props.breadcrumbs');
    } finally {
        app(Refilament::class)->panel()->breadcrumbs(true);
    }
});

it('drops the resource crumb when the page opts out via hasResourceBreadcrumbs', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(NoResourceCrumbResource::class);
    $refilament->registerPageRoutes();

    $this->get('/refilament/dashboardish', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(1, 'props.breadcrumbs')
        // Only the page crumb survives — no resource crumb above it.
        ->assertJsonPath('props.breadcrumbs.0.label', 'List')
        ->assertJsonMissingPath('props.breadcrumbs.0.url');
});

it('uses the resource breadcrumb override in the crumb', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(BrandedResource::class);
    $refilament->registerPageRoutes();

    $this->get('/refilament/branded', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.breadcrumbs.0.label', 'Blog posts');
});

it('serializes standalone page breadcrumbs when the page declares them', function () {
    $refilament = app(Refilament::class);

    $refilament->panel()->pages([BreadcrumbedSettingsPage::class]);
    $refilament->registerPageRoutes();

    $this->get('/refilament/breadcrumbed-settings-page', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonCount(2, 'props.breadcrumbs')
        ->assertJsonPath('props.breadcrumbs.0.label', 'Home')
        ->assertJsonPath('props.breadcrumbs.1.label', 'Settings');
});
