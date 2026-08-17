<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeClusterCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'refilament:make-cluster {name} {--force}';

    /**
     * The command description.
     */
    protected $description = 'Create a new Refilament page cluster class';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $filesystem): int
    {
        $name = Str::studly($this->argument('name'));
        $className = Str::endsWith($name, 'Cluster') ? $name : $name.'Cluster';

        $path = (string) config('refilament.panel.clusters_path', app_path('Refilament/Clusters'));
        $namespace = (string) config('refilament.panel.clusters_namespace', 'App\\\\Refilament\\\\Clusters');

        $file = $path.'/'.$className.'.php';

        if ($filesystem->exists($file) && ! $this->option('force')) {
            $this->components->error("{$className} already exists.");

            return self::FAILURE;
        }

        $filesystem->ensureDirectoryExists($path);

        $filesystem->put($file, str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $filesystem->get(__DIR__.'/../../stubs/cluster.stub'),
        ));

        $this->components->info("{$className} created at {$file}.");

        $slug = (string) Str::slug(Str::kebab(Str::beforeLast($className, 'Cluster')));

        $this->components->info("Group pages or resources under it by declaring 'protected static ?string \$cluster = {$className}::class;' on them — the cluster shows at /refilament/{$slug} and redirects to its first accessible member.");

        return self::SUCCESS;
    }
}
