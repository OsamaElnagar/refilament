<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakePageCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'refilament:make-page {name} {--resource=} {--force}';

    /**
     * The command description.
     */
    protected $description = 'Create a new Refilament page class (resource or standalone)';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $filesystem): int
    {
        $name = Str::studly($this->argument('name'));
        $className = Str::endsWith($name, 'Page') ? $name : $name.'Page';

        $resource = $this->option('resource');

        if (is_string($resource) && ! class_exists($resource)) {
            $this->components->error("Resource [{$resource}] does not exist.");

            return self::FAILURE;
        }

        if (is_string($resource)) {
            $path = (string) config('refilament.resources.path', app_path('Refilament/Resources')).'/Pages';
            $namespace = (string) config('refilament.resources.namespace', 'App\\Refilament\\Resources').'\\Pages';
            $stub = $filesystem->get(__DIR__.'/../../stubs/page-resource.stub');
            $slug = Str::kebab(Str::beforeLast($className, 'Page'));
        } else {
            $path = app_path('Refilament/Pages');
            $namespace = 'App\\Refilament\\Pages';
            $stub = $filesystem->get(__DIR__.'/../../stubs/page.stub');
            $slug = Str::kebab(Str::beforeLast($className, 'Page'));
        }

        $file = $path.'/'.$className.'.php';

        if ($filesystem->exists($file) && ! $this->option('force')) {
            $this->components->error("{$className} already exists.");

            return self::FAILURE;
        }

        $filesystem->ensureDirectoryExists($path);

        $filesystem->put($file, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ resource }}', '{{ component }}', '{{ slug }}'],
            [$namespace, $className, $resource ?? 'null', 'refilament/'.$slug, $slug],
            $stub,
        ));

        $this->components->info("{$className} created at {$file}.");

        if (is_string($resource)) {
            $this->components->info("Register it in the resource's getPages() map — e.g. '{$slug}' => {$className}::route('/{$slug}') — then add the refilament/{$slug} React component under resources/js/pages/refilament/.");
        } else {
            $this->components->info("Wire a route to it and add the refilament/{$slug} React component under resources/js/pages/refilament/.");
        }

        return self::SUCCESS;
    }
}
