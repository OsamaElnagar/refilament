<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeSingularResourceCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'refilament:make-singular-resource {name} {--model=} {--force}';

    /**
     * The command description.
     */
    protected $description = 'Create a new singular-resource page bound to one record (auto-created on first save)';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $filesystem): int
    {
        $name = Str::studly($this->argument('name'));
        $className = Str::endsWith($name, 'Page') ? $name : $name.'Page';

        $model = $this->option('model');

        if (! is_string($model) || $model === '' || ! class_exists($model)) {
            $this->components->error('The --model option must name an existing Eloquent model class, e.g. --model=App\\Models\\SiteSetting.');

            return self::FAILURE;
        }

        $path = app_path('Refilament/Pages');
        $namespace = 'App\\Refilament\\Pages';
        $slug = Str::kebab(Str::beforeLast($className, 'Page'));
        $file = $path.'/'.$className.'.php';

        if ($filesystem->exists($file) && ! $this->option('force')) {
            $this->components->error("{$className} already exists.");

            return self::FAILURE;
        }

        $filesystem->ensureDirectoryExists($path);

        $filesystem->put($file, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ model }}', '{{ slug }}'],
            [$namespace, $className, $model, $slug],
            $filesystem->get(__DIR__.'/../../stubs/singular-resource.stub'),
        ));

        $this->components->info("{$className} created at {$file}.");

        $this->components->info("It's served at /refilament/{$slug} — the form edits the first {{ model }} record and creates it on the first save. Define the fields in form(); no app-side route or React component needed.");

        return self::SUCCESS;
    }
}
