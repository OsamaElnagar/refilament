<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Resource;
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
