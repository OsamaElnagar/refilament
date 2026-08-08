<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Refilament\Refilament\RefilamentServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

use function Orchestra\Testbench\default_skeleton_path;

return Application::configure(basePath: $APP_BASE_PATH ?? default_skeleton_path())
    // Register the package and workbench providers explicitly. The normal test
    // boot injects them through testbench's config override, but `route:cache`
    // boots a *fresh* app via this bootstrap file (getFreshApplication() →
    // require workbench/bootstrap/app.php) and skips that injection, so without
    // this the cached route collection would drop every package route. Keeping
    // them listed here makes the fresh boot behave like the test boot
    // (docs/ROADMAP.md "1.6 Page system" — the generated page routes must stay
    // cacheable).
    ->withProviders([
        RefilamentServiceProvider::class,
        WorkbenchServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
