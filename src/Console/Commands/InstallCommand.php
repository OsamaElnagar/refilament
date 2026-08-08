<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * `refilament:install` — the Filament-style first-run command.
 *
 * Publishes the package's config, prebuilt assets and migrations, then
 * generates the consumer-owned panel provider (app/Providers/
 * RefilamentPanelProvider.php) and registers it in bootstrap/providers.php.
 * From that point the consumer owns the panel: `panel(Panel $panel)` chains
 * identity, path, colors, middleware, widgets and render hooks on top of the
 * config-seeded defaults (see PanelProvider).
 */
class InstallCommand extends Command
{
    protected $signature = 'refilament:install';

    protected $description = 'Install Refilament — publish assets, config and migrations, and generate the panel provider.';

    public function handle(Filesystem $filesystem): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'refilament-config', '--force' => true]);
        $this->callSilently('vendor:publish', ['--tag' => 'refilament-assets', '--force' => true]);
        $this->callSilently('vendor:publish', ['--tag' => 'refilament-migrations', '--force' => true]);

        $this->writePanelProvider($filesystem);
        $this->registerPanelProvider($filesystem);

        $this->components->info('Refilament is installed.');

        $this->components->bulletList([
            'Panel provider: '.$this->providerPath(),
            'Run `php artisan migrate` to create the notifications table (only if you use database notifications).',
            'Open '.$this->panelPathUrl().' in your browser.',
        ]);

        return self::SUCCESS;
    }

    protected function panelPathUrl(): string
    {
        $path = (string) config('refilament.panel.path', 'refilament');

        return '/'.ltrim($path, '/');
    }

    protected function providerPath(): string
    {
        return app_path('Providers/RefilamentPanelProvider.php');
    }

    protected function providerClass(): string
    {
        return 'App\\Providers\\RefilamentPanelProvider';
    }

    protected function writePanelProvider(Filesystem $filesystem): void
    {
        $path = $this->providerPath();

        if ($filesystem->exists($path)) {
            $this->components->warn('Panel provider already exists at '.$path.' — leaving it unchanged.');

            return;
        }

        $stub = $filesystem->get(__DIR__.'/../../stubs/panel-provider.stub');

        $filesystem->ensureDirectoryExists(dirname($path));

        $filesystem->put(
            $path,
            str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ path }}'],
                ['App\\Providers', 'RefilamentPanelProvider', (string) config('refilament.panel.path', 'refilament')],
                $stub,
            ),
        );

        $this->components->info('Created '.$path.'.');
    }

    protected function registerPanelProvider(Filesystem $filesystem): void
    {
        $path = base_path('bootstrap/providers.php');

        if (! $filesystem->exists($path)) {
            $this->components->warn(
                'bootstrap/providers.php not found — register '.$this->providerClass().' in your service providers manually.',
            );

            return;
        }

        $contents = $filesystem->get($path);

        if (str_contains($contents, $this->providerClass())) {
            return;
        }

        $trimmed = rtrim($contents);

        if (! str_ends_with($trimmed, '];')) {
            $this->components->warn(
                'Could not auto-register '.$this->providerClass().' — unexpected bootstrap/providers.php format. Register it manually.',
            );

            return;
        }

        // Insert the new entry before the closing '];', ensuring the previous
        // last entry keeps a trailing comma — a hand-written providers.php
        // without one must not become invalid PHP.
        $body = rtrim(substr($trimmed, 0, -2));

        $filesystem->put(
            $path,
            $body.(str_ends_with($body, ',') ? '' : ',')."\n    {$this->providerClass()}::class,\n];\n",
        );

        $this->components->info('Registered '.$this->providerClass().' in '.$path.'.');
    }
}
