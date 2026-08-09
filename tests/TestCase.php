<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests;

use Illuminate\Session\SessionManager;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    public function post($uri, array $data = [], array $headers = [])
    {
        return parent::post($uri, $this->withCsrfToken($data), $headers);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::postJson($uri, $this->withCsrfToken($data), $headers, $options);
    }

    /**
     * Start a session and merge its real token into the payload, so the
     * request follows the exact path a browser POST takes (session cookie →
     * StartSession → token match) instead of the framework's env=testing CSRF
     * bypass. The `_token` input is what PreventRequestForgery compares
     * against the session token; the shell sends the same value in an
     * `X-CSRF-TOKEN` header, which the middleware also accepts.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCsrfToken(array $data): array
    {
        $this->startSession();

        /** @var SessionManager $session */
        $session = $this->app['session'];

        return array_merge(['_token' => $session->token()], $data);
    }

    protected function defineEnvironment($app): void
    {
        // Tests don't load the `env` block from testbench.yaml (serve does), so
        // the app key is mirrored here. Keep both in sync.
        $app['config']->set('app.key', 'base64:zYxR6/Vy/APuB1HLXWfsn+bewgcSH7a8bNxk37vnkO0=');

        // The workbench's .env.example declares SESSION_DRIVER=cookie, which
        // leaks into the test env. The cookie driver needs a live request's
        // cookies, so pre-starting a session in the test helpers (the CSRF
        // token) would crash; `array` keeps sessions in-memory per test, which
        // is all the POST suite needs to exercise the session + CSRF pipeline.
        $app['config']->set('session.driver', 'array');

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
