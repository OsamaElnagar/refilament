<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Tests\Fixtures\RecordActionsNoEditResource;
use Refilament\Refilament\Tests\Fixtures\RecordActionsResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class LockedPostsPolicy
{
    // Guest-opt-in first parameter (Laravel 13), mirroring the fixture policy
    // in the authorization tests — the denial below is the policy's own call.
    public function create(?User $user = null): bool
    {
        return false;
    }
}

it('serves the default CreateAction on the list page header', function () {
    $response = $this->get('/refilament/posts', ['X-Inertia' => 'true']);

    $response->assertOk();

    // The default header action (slice 1.10): named 'create', labelled
    // "New Post" from the resource's model, with the plus icon and the
    // create page URL — Filament's getDefaultActionUrl: the create page
    // wins when the resource registers one.
    $response->assertJsonCount(1, 'props.pageActions');
    $response->assertJsonPath('props.pageActions.0.name', 'create');
    $response->assertJsonPath('props.pageActions.0.label', 'New Post');
    $response->assertJsonPath('props.pageActions.0.icon', 'plus');
    $response->assertJsonPath(
        'props.pageActions.0.url',
        route('refilament.resource.create', ['resource' => 'posts']),
    );
    $response->assertJsonMissingPath('props.pageActions.0.type');
    $response->assertJsonMissingPath('props.pageActions.0.schema');
});

it('serves the list page header widgets above the table', function () {
    Post::factory()->count(2)->create();

    $response = $this->get('/refilament/posts', ['X-Inertia' => 'true']);

    $response->assertOk()
        // The stats strip the demo ListPosts page registers (slice 1.10).
        ->assertJsonCount(1, 'props.headerWidgets')
        ->assertJsonPath('props.headerWidgets.0.type', 'stats_overview')
        ->assertJsonPath('props.headerWidgets.0.heading', 'Content overview')
        ->assertJsonCount(4, 'props.headerWidgets.0.stats')
        ->assertJsonPath('props.headerWidgetsColumns', 2);
});

it('omits pageActions on pages that declare none', function () {
    $this->get('/refilament/posts/stats', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.pageActions')
        ->assertJsonMissingPath('props.headerWidgets');
});

it('drops the default CreateAction when the current user cannot create', function () {
    Gate::policy(Post::class, LockedPostsPolicy::class);

    try {
        $this->get('/refilament/posts', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonMissingPath('props.pageActions');
    } finally {
        Gate::policy(Post::class, null);
    }
});

it('falls back to a modal create for a resource without a create page', function () {
    $refilament = app(Refilament::class);

    $class = new class extends Resource
    {
        protected static ?string $model = Post::class;

        protected static ?string $tableId = 'no-create';

        public static function table(Table $table): Table
        {
            return $table->id('no-create')->query(Post::query());
        }

        public static function form(Schema $schema): Schema
        {
            return $schema->id('no-create-form');
        }

        public static function getPages(): array
        {
            return [
                'index' => ListRecords::route('/'),
            ];
        }
    };

    $refilament->registerResources($class::class);
    $refilament->registerPageRoutes();

    $this->get('/refilament/no-create', ['X-Inertia' => 'true'])
        ->assertOk()
        // No create page → the CreateAction serializes as a modal hosting
        // the resource's form (addressed by its derived form id) instead
        // of a URL.
        ->assertJsonPath('props.pageActions.0.name', 'create')
        ->assertJsonPath('props.pageActions.0.label', 'New Post')
        ->assertJsonPath('props.pageActions.0.type', 'create')
        ->assertJsonPath('props.pageActions.0.schema', $class::getFormId())
        ->assertJsonMissingPath('props.pageActions.0.url');
});

it('resolves EditAction and ViewAction to per-record page URLs on record pages', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(RecordActionsResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create();

    // The view page's header: EditAction and ViewAction both resolve their
    // per-record URL through the resource's page map (the resource registers
    // the edit and view pages), so the buttons navigate instead of rendering
    // disabled — Filament's page-vs-modal semantics with page routes present.
    $this->get('/refilament/record-actions/'.$post->getKey(), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.pageActions.0.name', 'edit')
        ->assertJsonPath(
            'props.pageActions.0.url',
            route('refilament.resource.edit', ['resource' => 'record-actions', 'record' => $post->getKey()]),
        )
        ->assertJsonMissingPath('props.pageActions.0.actionUrl')
        ->assertJsonPath('props.pageActions.1.name', 'view')
        ->assertJsonPath(
            'props.pageActions.1.url',
            route('refilament.resource.view', ['resource' => 'record-actions', 'record' => $post->getKey()]),
        );
});

it('drops a record-scoped EditAction when the resource has no edit page', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(RecordActionsNoEditResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create();

    // No 'edit' page in the resource's map → getRecordUrl('edit') resolves
    // nothing → the EditAction button is dropped, never rendered dead.
    $this->get('/refilament/record-actions-no-edit/'.$post->getKey(), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonMissingPath('props.pageActions');
});

it('serializes a DeleteAction with an endpoint and a list-page redirect on record pages', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(RecordActionsResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create();

    // The edit page's header: the DeleteAction serializes the typed endpoint
    // (the client POSTs to run it) plus the list-page URL it lands on after
    // deleting — reloading in place would 404, the record is gone.
    $this->get('/refilament/record-actions/'.$post->getKey().'/edit', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.pageActions.0.name', 'delete')
        ->assertJsonPath('props.pageActions.0.requiresConfirmation', true)
        ->assertJsonPath(
            'props.pageActions.0.actionUrl',
            route('refilament.resource.action', [
                'resource' => 'record-actions',
                'page' => 'edit',
                'record' => $post->getKey(),
                'action' => 'delete',
            ]),
        )
        ->assertJsonPath('props.pageActions.0.redirect', RecordActionsResource::getNavigationUrl())
        ->assertJsonMissingPath('props.pageActions.0.url');
});

it('runs a record-scoped page action through the typed endpoint', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(RecordActionsResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create();

    $this->post('/refilament/record-actions/page/edit/record/'.$post->getKey().'/action/delete')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Record deleted.');

    // The DeleteAction closure ran — the record is gone.
    expect(Post::find($post->getKey()))->toBeNull();
});

it('refuses an unknown record-scoped page action', function () {
    $refilament = app(Refilament::class);

    $refilament->registerResources(RecordActionsResource::class);
    $refilament->registerPageRoutes();

    $post = Post::factory()->create();

    $this->post('/refilament/record-actions/page/view/record/'.$post->getKey().'/action/nope')
        ->assertNotFound();
});
