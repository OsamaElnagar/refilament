<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Tests\Fixtures\SingularSettingsPage;
use Workbench\App\Models\Post;

/**
 * Register the fixture singular page on the panel (the workbench's own pages
 * are replaced by it — the shared {page} route + resolvers rebuild for it).
 */
function singular_page_test_register(): void
{
    app(Refilament::class)->panel()->pages([SingularSettingsPage::class]);
    app(Refilament::class)->registerPageRoutes();
}

it('starts with an empty form when no record exists yet', function () {
    singular_page_test_register();

    $this->get('/refilament/singular-settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.formTitle', 'Singular Settings Page')
        ->assertJsonPath('props.description', 'A singular resource — one record, auto-created on first save.')
        ->assertJsonPath('props.hasUnsavedDataChangesAlert', true)
        // No record yet — the form opens with the fields' defaults only.
        ->assertJsonPath('props.data.title', null)
        ->assertJsonPath('props.data.slug', null)
        ->assertJsonPath('props.data.status', 'draft');

    expect(Post::count())->toBe(0);
});

it('auto-creates the record on the first save', function () {
    singular_page_test_register();

    $this->postJson('/refilament/schema/'.SingularSettingsPage::getFormId().'/submit', [
        'data' => ['title' => 'Homepage', 'author' => 'Ada', 'status' => 'draft', 'slug' => 'home'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Saved.');

    expect(Post::count())->toBe(1);

    $post = Post::first();
    expect($post->title)->toBe('Homepage')
        ->and($post->slug)->toBe('home')
        ->and($post->author)->toBe('Ada');
});

it('loads the existing record into the form on later visits', function () {
    singular_page_test_register();

    Post::create(['title' => 'Homepage', 'author' => 'Ada', 'status' => 'draft', 'slug' => 'home']);

    $this->get('/refilament/singular-settings', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.data.title', 'Homepage')
        ->assertJsonPath('props.data.slug', 'home')
        ->assertJsonPath('props.data.author', 'Ada');
});

it('updates the record instead of creating a duplicate on later saves', function () {
    singular_page_test_register();

    Post::create(['title' => 'Homepage', 'author' => 'Ada', 'status' => 'draft', 'slug' => 'home']);

    // The slug is unchanged — the unique rule must ignore the record's own
    // value (the record never rejects itself).
    $this->postJson('/refilament/schema/'.SingularSettingsPage::getFormId().'/submit', [
        'data' => ['title' => 'Homepage v2', 'author' => 'Ada', 'status' => 'draft', 'slug' => 'home'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Post::count())->toBe(1)
        ->and(Post::first()->title)->toBe('Homepage v2');
});

it('still rejects a slug owned by a different record', function () {
    singular_page_test_register();

    $record = Post::create(['title' => 'Homepage', 'author' => 'Ada', 'status' => 'draft', 'slug' => 'home']);
    Post::create(['title' => 'Other', 'author' => 'Bob', 'status' => 'draft', 'slug' => 'other']);

    // Submitting the OTHER record's slug is a real unique violation — only
    // the singular record's own values are ignored.
    $this->postJson('/refilament/schema/'.SingularSettingsPage::getFormId().'/submit', [
        'data' => ['title' => 'Homepage v2', 'author' => 'Ada', 'status' => 'draft', 'slug' => 'other'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.slug.0', 'The Slug has already been taken.');

    expect(Post::count())->toBe(2);
});

it('scaffolds a singular-resource page class with --model', function () {
    $path = sys_get_temp_dir().'/refilament-singular-'.Str::random(6);
    $namespace = 'App\\Refilament\\Pages';
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-singular-resource', [
            'name' => 'SiteSettings',
            '--model' => 'Workbench\\App\\Models\\Post',
        ])->assertSuccessful();
    } finally {
        $this->app->useAppPath($originalAppPath);
    }

    $file = $path.'/Refilament/Pages/SiteSettingsPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class SiteSettingsPage extends Page');
    expect($content)->toContain('protected static ?string $model = Workbench\\App\\Models\\Post::class;');
    expect($content)->toContain('protected static bool $hasUnsavedDataChangesAlert = true;');
    expect($content)->toContain("return 'refilament/page-form';");
    expect($content)->toContain('function form(Schema $schema): Schema');

    unlink($file);
    rmdir($path.'/Refilament/Pages');
    rmdir($path.'/Refilament');
    rmdir($path);
});

it('rejects an unknown --model', function () {
    $this->artisan('refilament:make-singular-resource', [
        'name' => 'SiteSettings',
        '--model' => 'App\\Models\\MissingSetting',
    ])->assertExitCode(1);
});
