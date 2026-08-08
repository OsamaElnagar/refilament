<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Refilament\Refilament\Resources\Resource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('generates a resource file with columns and fields from the model table', function () {
    $path = sys_get_temp_dir().'/refilament-generated-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Post',
        '--model' => Post::class,
        '--generate' => true,
    ])->assertSuccessful();

    $file = $path.'/PostResource.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class PostResource extends Resource');
    expect($content)->toContain('protected static ?string $model = '.Str::afterLast(Post::class, '\\').'::class;');
    expect($content)->toContain("Column::make('id')->label('ID')->sortable(),");
    expect($content)->toContain("Column::make('title')");
    expect($content)->toContain("TextInput::make('title')");
    expect($content)->toContain("TextInput::make('views')");
    // Foreign-key and timestamp columns are skipped.
    expect($content)->not->toContain("Column::make('user_id')");
    expect($content)->not->toContain("Column::make('created_at')");

    // The generated class is valid PHP and loadable through a manual autoloader.
    spl_autoload_register(static function (string $class) use ($namespace, $path): void {
        if (str_starts_with($class, $namespace.'\\')) {
            $generated = $path.'/'.Str::after($class, $namespace.'\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\\PostResource';
    expect(is_subclass_of($class, Resource::class))->toBeTrue();
    expect($class::getTableId())->toBe('post');
    expect($class::getFormId())->toBe('post-form');

    unlink($file);
    rmdir($path);
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

    $content = file_get_contents($path.'/UserResource.php');

    // remember_token is auth-system noise — skipped from both table and form.
    expect($content)->not->toContain('remember_token');

    // The password column stays in the table but renders as a masked,
    // revealable password input in the form.
    expect($content)->toContain("Column::make('password')");
    expect($content)->toContain("TextInput::make('password')");
    expect($content)->toContain('->password()->revealable()');

    unlink($path.'/UserResource.php');
    rmdir($path);
});

it('generates a skeleton resource without --generate', function () {
    $path = sys_get_temp_dir().'/refilament-skeleton-'.Str::random(6);
    $namespace = 'Refilament\\Refilament\\Tests\\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-resource', [
        'name' => 'Category',
        '--model' => Post::class,
    ])->assertSuccessful();

    $file = $path.'/CategoryResource.php';
    expect(file_exists($file))->toBeTrue();
    expect(file_get_contents($file))->toContain('TODO: define columns');

    unlink($file);
    rmdir($path);
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

    unlink($path.'/PostResource.php');
    rmdir($path);
});

it('rejects an unknown model', function () {
    $this->artisan('refilament:make-resource', [
        'name' => 'Missing',
        '--model' => 'App\\Models\\Missing',
    ])->assertExitCode(1);
});
