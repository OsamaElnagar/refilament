<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Resource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('generates a self-contained resource directory with columns and fields from the model table', function () {
    $path = sys_get_temp_dir().'/refilament-generated-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model' => Post::class,
        '--generate' => true,
    ])->assertSuccessful();

    $resourceFile = $path.'/Posts/PostResource.php';
    $schemaFile = $path.'/Posts/Schemas/PostForm.php';
    $tableFile = $path.'/Posts/Tables/PostsTable.php';

    expect(file_exists($resourceFile))->toBeTrue();
    expect(file_exists($schemaFile))->toBeTrue();
    expect(file_exists($tableFile))->toBeTrue();

    // The thin resource delegates to the standalone classes.
    expect(file_get_contents($resourceFile))->toContain('class PostResource extends Resource');
    expect(file_get_contents($resourceFile))->toContain('PostsTable::configure($table)');
    expect(file_get_contents($resourceFile))->toContain('PostForm::configure($schema)');

    // The table carries the generated columns; the form the fields.
    expect(file_get_contents($tableFile))->toContain("Column::make('id')->label('ID')->sortable(),");
    expect(file_get_contents($tableFile))->toContain("Column::make('title')");
    expect(file_get_contents($schemaFile))->toContain("TextInput::make('title')");
    expect(file_get_contents($schemaFile))->toContain("TextInput::make('views')");
    // Foreign-key and timestamp columns are skipped.
    expect(file_get_contents($tableFile))->not->toContain("Column::make('user_id')");
    expect(file_get_contents($tableFile))->not->toContain("Column::make('created_at')");

    $resourceClass = $namespace.'\\Posts\\PostResource';

    // The generated classes are valid PHP and loadable through the PSR-4
    // mapping the command's own namespace implies.
    spl_autoload_register(static function (string $class) use ($namespace, $path): void {
        if (str_starts_with($class, $namespace.'\\')) {
            $generated = $path.'/'.Str::after($class, $namespace.'\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    expect(is_subclass_of($resourceClass, Resource::class))->toBeTrue();
    expect($resourceClass::getTableId())->toBe('post');
    expect($resourceClass::getFormId())->toBe('post-form');

    $refilament = new Refilament;
    $refilament->registerResourcesFromDirectory($path, $namespace);
    expect($refilament->getResourceClass('post'))->toBe($resourceClass);
});

it('skips auth columns and emits masked revealable inputs for password columns', function () {
    $path = sys_get_temp_dir().'/refilament-auth-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'User',
        '--model' => User::class,
        '--generate' => true,
    ])->assertSuccessful();

    $tableContent = file_get_contents($path.'/Users/Tables/UsersTable.php');
    $schemaContent = file_get_contents($path.'/Users/Schemas/UserForm.php');

    // remember_token is auth-system noise — skipped from both table and form.
    expect($tableContent)->not->toContain('remember_token');
    expect($schemaContent)->not->toContain('remember_token');

    // The password column stays in the table but renders as a masked,
    // revealable password input in the form.
    expect($tableContent)->toContain("Column::make('password')");
    expect($schemaContent)->toContain("TextInput::make('password')");
    expect($schemaContent)->toContain('->password()->revealable()');
});

it('generates the skeleton directory without --generate', function () {
    $path = sys_get_temp_dir().'/refilament-skeleton-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Category',
        '--model' => Post::class,
    ])->assertSuccessful();

    $resourceFile = $path.'/Categories/CategoryResource.php';
    $tableFile = $path.'/Categories/Tables/CategoriesTable.php';
    $schemaFile = $path.'/Categories/Schemas/CategoryForm.php';

    expect(file_exists($resourceFile))->toBeTrue();
    expect(file_get_contents($tableFile))->toContain('TODO: define columns');
    expect(file_get_contents($schemaFile))->toContain('TODO: define fields');
});

it('refuses to overwrite an existing resource without --force', function () {
    $path = sys_get_temp_dir().'/refilament-existing-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model' => Post::class,
    ])->assertSuccessful();

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model' => Post::class,
    ])->assertExitCode(1);

    // The per-resource directory is non-empty (Schemas/, Tables/), so it must
    // be removed recursively.
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
});

it('rejects an unknown model', function () {
    $this->artisan('refilament:make-resource', [
        'name' => 'Missing',
        '--model' => 'App\\Models\\Missing',
    ])->assertExitCode(1);
});

it('emits a soft-delete filter, record title and a datetime field for a soft-deleting model', function () {
    $path = sys_get_temp_dir().'/refilament-post-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model' => Post::class,
        '--generate' => true,
        '--view' => true,
    ])->assertSuccessful();

    $resource = file_get_contents($path.'/Posts/PostResource.php');
    $table = file_get_contents($path.'/Posts/Tables/PostsTable.php');
    $form = file_get_contents($path.'/Posts/Schemas/PostForm.php');
    $infolist = file_get_contents($path.'/Posts/Schemas/PostInfolist.php');

    // Soft-delete detection: a TrashedFilter is imported and wired into the table.
    expect($table)->toContain('use Refilament\Refilament\Tables\TrashedFilter;');
    expect($table)->toContain('->filters([TrashedFilter::make()])');

    // Record title auto-detected from the title column.
    expect($resource)->toContain("protected static ?string \$recordTitleAttribute = 'title';");

    // Non-skipped timestamp columns become date-time pickers in the form.
    expect($form)->toContain("DateTimePicker::make('published_at')");

    // --view generates a read-only infolist schema and wires it into the resource.
    expect($infolist)->toContain("TextEntry::make('title')");
    expect($resource)->toContain('PostInfolist::configure($schema)');
});

it('maps boolean and text columns to toggles, textareas and toggle columns', function () {
    $path = sys_get_temp_dir().'/refilament-comment-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Comment',
        '--model' => Comment::class,
        '--generate' => true,
    ])->assertSuccessful();

    $table = file_get_contents($path.'/Comments/Tables/CommentsTable.php');
    $form = file_get_contents($path.'/Comments/Schemas/CommentForm.php');

    expect($table)->toContain("ToggleColumn::make('is_visible')");
    expect($table)->toContain('use Refilament\Refilament\Tables\Columns\ToggleColumn;');
    expect($form)->toContain("Toggle::make('is_visible')");
    expect($form)->toContain("Textarea::make('content')");

    // Long-text columns clamp to a couple of lines so the table never
    // stretches horizontally to fit the full body.
    expect($table)->toContain("Column::make('content')->label('Content')->lineClamp(2),");

    // Comment does not soft-delete — no trashed filter is emitted.
    expect($table)->not->toContain('TrashedFilter');
});

it('resolves the model through --model-namespace when --model is omitted', function () {
    $path = sys_get_temp_dir().'/refilament-ns-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model-namespace' => 'Workbench\\App\\Models',
        '--generate' => true,
    ])->assertSuccessful();

    $resource = file_get_contents($path.'/Posts/PostResource.php');

    expect($resource)->toContain('use Workbench\App\Models\Post;');
});

it('generates record actions and record-page header actions by default', function () {
    $path = sys_get_temp_dir().'/refilament-actions-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model' => Post::class,
        '--generate' => true,
    ])->assertSuccessful();

    // The table ships per-row edit + delete record actions, with the imports
    // alphabetized ahead of the Table imports (pint-clean).
    $table = file_get_contents($path.'/Posts/Tables/PostsTable.php');

    expect($table)->toContain('use Refilament\Refilament\Actions\DeleteAction;');
    expect($table)->toContain('use Refilament\Refilament\Actions\EditAction;');
    expect($table)->toContain('->recordActions([');
    expect($table)->toContain('EditAction::make(),');
    expect($table)->toContain('DeleteAction::make(),');

    // The edit page carries view + delete header actions.
    $edit = file_get_contents($path.'/Posts/Pages/EditPost.php');

    expect($edit)->toContain('use Refilament\Refilament\Actions\ViewAction;');
    expect($edit)->toContain('getHeaderActions(string $resource)');
    expect($edit)->toContain('ViewAction::make(),');
    expect($edit)->toContain('DeleteAction::make()');

    // The view page carries edit + delete header actions.
    $view = file_get_contents($path.'/Posts/Pages/ViewPost.php');

    expect($view)->toContain('use Refilament\Refilament\Actions\EditAction;');
    expect($view)->toContain('getHeaderActions(string $resource)');
    expect($view)->toContain('EditAction::make(),');
    expect($view)->toContain('DeleteAction::make()');
});
