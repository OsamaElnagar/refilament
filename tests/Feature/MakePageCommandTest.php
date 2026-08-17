<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Refilament\Refilament\Clusters\Cluster;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Resources\Pages\Page as ResourcePage;
use Workbench\App\Refilament\Resources\PostResource;

it('generates a resource page class when --resource is given', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6).'/Resources/Pages';
    $namespace = 'Refilament\Refilament\Tests\Generated\Resources\Pages';

    config()->set('refilament.resources.path', dirname($path));
    config()->set('refilament.resources.namespace', 'Refilament\Refilament\Tests\Generated\Resources');

    $this->artisan('refilament:make-page', [
        'name' => 'PostStats',
        '--resource' => PostResource::class,
    ])->assertSuccessful();

    $file = $path.'/PostStatsPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class PostStatsPage extends Page');
    expect($content)->toContain('protected static ?string $resource = '.PostResource::class.'::class;');
    expect($content)->toContain("return 'refilament/post-stats';");
    expect($content)->toContain('function getViewData');

    // The generated class is valid PHP and loadable through a manual autoloader.
    spl_autoload_register(static function (string $class) use ($namespace, $path): void {
        if (str_starts_with($class, $namespace.'\\')) {
            $generated = $path.'/'.Str::after($class, $namespace.'\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\PostStatsPage';
    expect(is_subclass_of($class, ResourcePage::class))->toBeTrue();
    expect($class::getInertiaComponent())->toBe('refilament/post-stats');

    unlink($file);
    rmdir($path);
    rmdir(dirname($path));
});

it('generates a standalone page class without --resource', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $namespace = 'App\Refilament\Pages';
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Report',
        ])->assertSuccessful();
    } finally {
        $this->app->useAppPath($originalAppPath);
    }

    $file = $path.'/Refilament/Pages/ReportPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class ReportPage extends Page');
    expect($content)->toContain('use Refilament\Refilament\Pages\Page;');
    expect($content)->not->toContain('$resource');
    expect($content)->toContain("return 'refilament/report';");
    expect($content)->toContain('function getPanelViewData');

    spl_autoload_register(static function (string $class) use ($path): void {
        if (str_starts_with($class, 'App\Refilament\Pages\\')) {
            $generated = $path.'/Refilament/Pages/'.Str::after($class, 'App\Refilament\Pages\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    expect(is_subclass_of($namespace.'\ReportPage', Page::class))->toBeTrue();

    unlink($file);
    rmdir($path.'/Refilament/Pages');
    rmdir($path.'/Refilament');
    rmdir($path);
});

it('refuses to overwrite an existing page without --force', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $namespace = 'Refilament\Refilament\Tests\Generated';

    config()->set('refilament.resources.path', $path);
    config()->set('refilament.resources.namespace', $namespace);

    $this->artisan('refilament:make-page', [
        'name' => 'Stats',
        '--resource' => PostResource::class,
    ])->assertSuccessful();

    $this->artisan('refilament:make-page', [
        'name' => 'Stats',
        '--resource' => PostResource::class,
    ])->assertExitCode(1);

    unlink($path.'/Pages/StatsPage.php');
    rmdir($path.'/Pages');
    rmdir($path);
});

it('generates without appending Page to a name that ends in Page', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6).'/Resources/Pages';
    $namespace = 'Refilament\Refilament\Tests\Generated\Resources\Pages';

    config()->set('refilament.resources.path', dirname($path));
    config()->set('refilament.resources.namespace', 'Refilament\Refilament\Tests\Generated\Resources');

    $this->artisan('refilament:make-page', [
        'name' => 'MetricsPage',
        '--resource' => PostResource::class,
    ])->assertSuccessful();

    expect(file_exists($path.'/MetricsPage.php'))->toBeTrue();
    expect(file_get_contents($path.'/MetricsPage.php'))->toContain('class MetricsPage extends Page');

    unlink($path.'/MetricsPage.php');
    rmdir($path);
    rmdir(dirname($path));
});

it('generates a page-form class with --form', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $namespace = 'App\Refilament\Pages';
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Settings',
            '--form' => true,
        ])->assertSuccessful();
    } finally {
        $this->app->useAppPath($originalAppPath);
    }

    $file = $path.'/Refilament/Pages/SettingsPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class SettingsPage extends Page');
    expect($content)->toContain('use Refilament\Refilament\Schemas\Schema;');
    expect($content)->toContain('protected static bool $hasUnsavedDataChangesAlert = true;');
    // The generic page-form component — zero consumer React code needed.
    expect($content)->toContain("return 'refilament/page-form';");
    expect($content)->toContain('function form(Schema $schema): Schema');
    expect($content)->toContain('function getPanelViewData');

    spl_autoload_register(static function (string $class) use ($path): void {
        if (str_starts_with($class, 'App\Refilament\Pages\\')) {
            $generated = $path.'/Refilament/Pages/'.Str::after($class, 'App\Refilament\Pages\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\SettingsPage';
    expect(is_subclass_of($class, Page::class))->toBeTrue();
    expect($class::getInertiaComponent())->toBe('refilament/page-form');
    expect($class::hasUnsavedDataChangesAlert())->toBeTrue();

    unlink($file);
    rmdir($path.'/Refilament/Pages');
    rmdir($path.'/Refilament');
    rmdir($path);
});

it('generates a page-table class with --table', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $namespace = 'App\Refilament\Pages';
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Inventory',
            '--table' => true,
        ])->assertSuccessful();
    } finally {
        $this->app->useAppPath($originalAppPath);
    }

    $file = $path.'/Refilament/Pages/InventoryPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class InventoryPage extends Page');
    expect($content)->toContain('use Refilament\Refilament\Tables\Table;');
    expect($content)->toContain("return 'refilament/page-table';");
    expect($content)->toContain('function table(Table $table): Table');
    expect($content)->toContain('function getPanelViewData');

    spl_autoload_register(static function (string $class) use ($path): void {
        if (str_starts_with($class, 'App\Refilament\Pages\\')) {
            $generated = $path.'/Refilament/Pages/'.Str::after($class, 'App\Refilament\Pages\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\InventoryPage';
    expect(is_subclass_of($class, Page::class))->toBeTrue();
    expect($class::getInertiaComponent())->toBe('refilament/page-table');
    expect($class::getTableId())->toContain('inventory-page');

    unlink($file);
    rmdir($path.'/Refilament/Pages');
    rmdir($path.'/Refilament');
    rmdir($path);
});

it('generates a page-infolist class with --infolist', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $namespace = 'App\Refilament\Pages';
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Read',
            '--infolist' => true,
        ])->assertSuccessful();
    } finally {
        $this->app->useAppPath($originalAppPath);
    }

    $file = $path.'/Refilament/Pages/ReadPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class ReadPage extends Page');
    expect($content)->toContain('use Refilament\Refilament\Infolists\Components\TextEntry;');
    expect($content)->toContain("return 'refilament/page-infolist';");
    expect($content)->toContain('function infolist(Schema $schema): Schema');
    expect($content)->toContain('function getPanelViewData');

    spl_autoload_register(static function (string $class) use ($path): void {
        if (str_starts_with($class, 'App\Refilament\Pages\\')) {
            $generated = $path.'/Refilament/Pages/'.Str::after($class, 'App\Refilament\Pages\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\ReadPage';
    expect(is_subclass_of($class, Page::class))->toBeTrue();
    expect($class::getInertiaComponent())->toBe('refilament/page-infolist');
    expect($class::getInfolistId())->toContain('read-page');

    unlink($file);
    rmdir($path.'/Refilament/Pages');
    rmdir($path.'/Refilament');
    rmdir($path);
});

it('generates a record-scoped resource page with --record', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6).'/Resources/Pages';
    $namespace = 'Refilament\Refilament\Tests\Generated\Resources\Pages';

    config()->set('refilament.resources.path', dirname($path));
    config()->set('refilament.resources.namespace', 'Refilament\Refilament\Tests\Generated\Resources');

    $this->artisan('refilament:make-page', [
        'name' => 'ManagePost',
        '--resource' => PostResource::class,
        '--record' => true,
    ])->assertSuccessful();

    $file = $path.'/ManagePostPage.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class ManagePostPage extends Page');
    expect($content)->toContain('protected static string $routePath = \'/{record}/manage\';');
    expect($content)->toContain("return 'refilament/page-infolist';");
    expect($content)->toContain('function infolist(Schema $schema): Schema');
    expect($content)->toContain('static::resolveRecord($resource, (string) $record);');

    spl_autoload_register(static function (string $class) use ($namespace, $path): void {
        if (str_starts_with($class, $namespace.'\\')) {
            $generated = $path.'/'.Str::after($class, $namespace.'\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\ManagePostPage';
    expect(is_subclass_of($class, ResourcePage::class))->toBeTrue();
    expect($class::getInertiaComponent())->toBe('refilament/page-infolist');

    unlink($file);
    rmdir($path);
    rmdir(dirname($path));
});

it('rejects --record without a resource', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Manage',
            '--record' => true,
        ])->assertExitCode(1);
    } finally {
        $this->app->useAppPath($originalAppPath);
    }
});

it('rejects combining --form and --table', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Both',
            '--form' => true,
            '--table' => true,
        ])->assertExitCode(1);
    } finally {
        $this->app->useAppPath($originalAppPath);
    }
});

it('generates a cluster class', function () {
    $path = sys_get_temp_dir().'/refilament-cluster-'.Str::random(6);
    $namespace = 'App\Refilament\Clusters';
    $originalAppPath = $this->app->path();

    config()->set('refilament.panel.clusters_path', $path);
    config()->set('refilament.panel.clusters_namespace', $namespace);

    $this->artisan('refilament:make-cluster', [
        'name' => 'Account',
    ])->assertSuccessful();

    $file = $path.'/AccountCluster.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class AccountCluster extends Cluster');
    expect($content)->toContain('use Refilament\Refilament\Clusters\Cluster;');

    spl_autoload_register(static function (string $class) use ($namespace, $path): void {
        if (str_starts_with($class, $namespace.'\\')) {
            $generated = $path.'/'.Str::after($class, $namespace.'\\').'.php';

            if (is_file($generated)) {
                require_once $generated;
            }
        }
    });

    $class = $namespace.'\AccountCluster';
    expect(is_subclass_of($class, Cluster::class))->toBeTrue();
    expect($class::getSlug())->toBe('account');

    unlink($file);
    rmdir($path);
    $this->app->useAppPath($originalAppPath);
});

it('rejects an unknown resource', function () {
    $this->artisan('refilament:make-page', [
        'name' => 'Stats',
        '--resource' => 'App\Refilament\Resources\MissingResource',
    ])->assertExitCode(1);
});

it('rejects combining --infolist with --form', function () {
    $path = sys_get_temp_dir().'/refilament-page-'.Str::random(6);
    $originalAppPath = $this->app->path();

    $this->app->useAppPath($path);

    try {
        $this->artisan('refilament:make-page', [
            'name' => 'Hybrid',
            '--infolist' => true,
            '--form' => true,
        ])->assertExitCode(1);
    } finally {
        $this->app->useAppPath($originalAppPath);
    }
});
