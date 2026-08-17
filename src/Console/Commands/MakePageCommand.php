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
    protected $signature = 'refilament:make-page {name} {--resource=} {--form} {--table} {--infolist} {--record} {--force}';

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

        $slug = Str::kebab(Str::beforeLast($className, 'Page'));

        if (is_string($resource)) {
            $path = (string) config('refilament.resources.path', app_path('Refilament/Resources')).'/Pages';
            $namespace = (string) config('refilament.resources.namespace', 'App\\Refilament\\Resources').'\\Pages';

            // A resource page scoped to a record (the record-pages slice —
            // served at /{resource}/{record}/manage) gets the record stub: an
            // infolist host whose entries read the URL record, with the
            // resolveRecord() payload wired. Form/table/infolist flags do not
            // apply to it (the stub is a complete record page).
            if ($this->option('record')) {
                if ($this->option('form') || $this->option('table') || $this->option('infolist')) {
                    $this->components->error('A --record page is a complete scaffold — combine it with neither --form, --table nor --infolist; edit the generated class instead.');

                    return self::FAILURE;
                }

                $stub = $filesystem->get(__DIR__.'/../../stubs/page-record-resource.stub');
            } else {
                $stub = $filesystem->get(__DIR__.'/../../stubs/page-resource.stub');
            }
        } else {
            if ($this->option('record')) {
                $this->components->error('A --record page belongs to a resource — pass --resource=App\\Refilament\\Resources\\YourResource.');

                return self::FAILURE;
            }

            $path = app_path('Refilament/Pages');
            $namespace = 'App\\Refilament\\Pages';
            $stub = $filesystem->get(__DIR__.'/../../stubs/page.stub');

            // A standalone page hosting a form (the page-forms slice), a
            // table (the pages-as-tables slice) or an infolist (the
            // page-infolists slice) gets its flavoured stub and renders the
            // matching generic component — zero consumer React code.
            // Declaring more than one host is a config error surfaced at
            // runtime (the form/table payloads share the `id` key), so it's
            // rejected here.
            $hosts = collect(['form', 'table', 'infolist'])->filter(fn (string $flag): bool => (bool) $this->option($flag));

            if ($hosts->count() > 1) {
                $this->components->error('A page cannot combine --form, --table and --infolist — declare one host per page.');

                return self::FAILURE;
            }

            if ($hosts->contains('form')) {
                $stub = $filesystem->get(__DIR__.'/../../stubs/page-form.stub');
            } elseif ($hosts->contains('table')) {
                $stub = $filesystem->get(__DIR__.'/../../stubs/page-table.stub');
            } elseif ($hosts->contains('infolist')) {
                $stub = $filesystem->get(__DIR__.'/../../stubs/page-infolist.stub');
            }
        }

        $file = $path.'/'.$className.'.php';

        if ($filesystem->exists($file) && ! $this->option('force')) {
            $this->components->error("{$className} already exists.");

            return self::FAILURE;
        }

        $filesystem->ensureDirectoryExists($path);

        $filesystem->put($file, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ resource }}', '{{ component }}', '{{ slug }}'],
            [
                $namespace,
                $className,
                $resource ?? 'null',
                match (true) {
                    $this->option('record') => 'refilament/page-infolist',
                    $this->option('form') && ! is_string($resource) => 'refilament/page-form',
                    $this->option('table') && ! is_string($resource) => 'refilament/page-table',
                    $this->option('infolist') && ! is_string($resource) => 'refilament/page-infolist',
                    default => 'refilament/'.$slug,
                },
                $slug,
            ],
            $stub,
        ));

        $this->components->info("{$className} created at {$file}.");

        if (is_string($resource) && $this->option('record')) {
            $this->components->info("Register it in the resource's getPages() map — e.g. 'manage' => {$className}::route('/{record}/manage') — and the page renders the generic refilament/page-infolist component: define the entries in infolist(), and /refilament/{$resource}/1/manage reads the record with no app-side route or React component.");
        } elseif (is_string($resource)) {
            $this->components->info("Register it in the resource's getPages() map — e.g. '{$slug}' => {$className}::route('/{$slug}') — then add the refilament/{$slug} React component under resources/js/pages/refilament/.");
        } elseif ($this->option('form')) {
            $this->components->info("The page renders the generic refilament/page-form component — define the form in form(), and it's served at /refilament/{$slug} with no app-side route or React component.");
        } elseif ($this->option('table')) {
            $this->components->info("The page renders the generic refilament/page-table component — define the table in table(), and it's served at /refilament/{$slug} with no app-side route or React component.");
        } elseif ($this->option('infolist')) {
            $this->components->info("The page renders the generic refilament/page-infolist component — define the entries in infolist(), and it's served at /refilament/{$slug} with no app-side route or React component.");
        } else {
            $this->components->info("Add the refilament/{$slug} React component under resources/js/pages/refilament/ — the page is served at /refilament/{$slug} with no app-side route.");
        }

        return self::SUCCESS;
    }
}
