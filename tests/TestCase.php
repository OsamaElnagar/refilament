<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function defineEnvironment($app): void
    {
        // Tests don't load the `env` block from testbench.yaml (serve does), so
        // the app key is mirrored here. Keep both in sync.
        $app['config']->set('app.key', 'base64:zYxR6/Vy/APuB1HLXWfsn+bewgcSH7a8bNxk37vnkO0=');

        // Isolate every test on a fresh in-memory sqlite database. The default
        // file-based sqlite persists across tests *and* runs — rows like the
        // per-post users accumulate and eventually collide on unique
        // constraints (fake()->unique() only guarantees uniqueness within one
        // test). Testbench detects `:memory:` and resets it per test.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function defineDatabaseMigrations(): void
    {
        // The testbench skeleton migrations (users/cache/jobs) run on the
        // serve path but not automatically in tests — load them alongside the
        // workbench migrations (e.g. the demo `posts` table) so factories
        // referencing User have a table on the fresh in-memory database.
        $this->loadMigrationsFrom([
            '--path' => [
                __DIR__.'/../vendor/orchestra/testbench-core/laravel/migrations',
                __DIR__.'/../workbench/database/migrations',
            ],
        ]);
    }
}
