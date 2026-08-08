<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MakeResourceCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'refilament:make-resource {name} {--model=} {--generate} {--force}';

    /**
     * The command description.
     */
    protected $description = 'Create a new Refilament resource class';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $filesystem): int
    {
        $name = Str::studly($this->argument('name'));
        $className = $name.'Resource';

        /** @var class-string<Model> $model */
        $model = $this->option('model') ?? 'App\\Models\\'.$name;

        $path = (string) config('refilament.resources.path', app_path('Refilament/Resources'));
        $namespace = (string) config('refilament.resources.namespace', 'App\\Refilament\\Resources');

        $file = $path.'/'.$className.'.php';

        if ($filesystem->exists($file) && ! $this->option('force')) {
            $this->components->error("{$className} already exists.");

            return self::FAILURE;
        }

        if (! class_exists($model)) {
            $this->components->error("Model [{$model}] does not exist.");

            return self::FAILURE;
        }

        [$tableBody, $formBody] = $this->option('generate')
            ? $this->generateBodies($model)
            : [self::tablePlaceholder(), self::formPlaceholder()];

        // The stub embeds the bodies one level past the `->columns([` /
        // `->components([` chain lines (12 spaces + 4) — indent every line
        // so the generated file is pint-clean out of the box.
        $tableBody = $this->indent($tableBody, 16);
        $formBody = $this->indent($formBody, 16);

        $filesystem->ensureDirectoryExists($path);

        $stub = $filesystem->get(__DIR__.'/../../stubs/resource.stub');

        $filesystem->put($file, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ modelImport }}', '{{ modelShort }}', '{{ tableBody }}', '{{ formBody }}'],
            [$namespace, $className, $model, Str::afterLast($model, '\\'), $tableBody, $formBody],
            $stub,
        ));

        $this->components->info("{$className} created at {$file}.");

        if ($this->option('generate')) {
            $this->components->info('Generated columns and fields from the model table — customize the resource before shipping.');
        } else {
            $this->components->info('Define columns and fields in the generated table() and form() methods.');
        }

        return self::SUCCESS;
    }

    /**
     * Build the table columns and form fields from the model's table columns.
     *
     * @param  class-string<Model>  $model
     * @return array{0: string, 1: string}
     */
    protected function generateBodies(string $model): array
    {
        $table = (new $model)->getTable();
        $columns = Schema::getColumns($table);

        $tableColumns = [];
        $formFields = [];

        foreach ($columns as $column) {
            $name = (string) $column['name'];
            $type = (string) $column['type_name'];
            $nullable = (bool) $column['nullable'];
            $label = Str::headline($name);

            if ($name === 'id') {
                $tableColumns[] = "Column::make('id')->label('ID')->sortable(),";

                continue;
            }

            if ($this->isSkippedColumn($name)) {
                continue;
            }

            if ($this->isIntegerType($type)) {
                $tableColumns[] = "Column::make('{$name}')->label('{$label}'),";
                $formFields[] = "TextInput::make('{$name}')->label('{$label}')".($nullable ? '' : '->required()').'->integer(),';

                continue;
            }

            if ($this->isTemporalType($type)) {
                $tableColumns[] = "Column::make('{$name}')->label('{$label}')->sortable(),";

                continue;
            }

            // Strings, text and anything else land as a text input (a textarea
            // component is deferred). Password-named columns become masked,
            // revealable password inputs.
            $length = isset($column['length']) && is_numeric($column['length'])
                ? (int) $column['length']
                : null;

            $tableColumns[] = "Column::make('{$name}')->label('{$label}'),";
            $formFields[] = "TextInput::make('{$name}')->label('{$label}')"
                .($nullable ? '' : '->required()')
                .($this->isPasswordColumn($name) ? '->password()->revealable()' : '')
                .($length !== null ? "->maxLength({$length})" : '')
                .',';
        }

        if ($tableColumns === []) {
            $tableColumns[] = self::tablePlaceholder();
        }

        if ($formFields === []) {
            $formFields[] = self::formPlaceholder();
        }

        return [implode("\n", $tableColumns), implode("\n", $formFields)];
    }

    protected static function tablePlaceholder(): string
    {
        return '// TODO: define columns — e.g.';
    }

    protected static function formPlaceholder(): string
    {
        return '// TODO: define fields — e.g.';
    }

    /**
     * Indent every non-empty line of a generated body to match the stub's
     * nesting, keeping the output tidy without a formatter pass.
     */
    protected function indent(string $body, int $spaces): string
    {
        $padding = str_repeat(' ', $spaces);

        return implode("\n", array_map(
            static fn (string $line): string => $line === '' ? '' : $padding.$line,
            explode("\n", $body),
        ));
    }

    /**
     * Columns the generator skips: timestamps, auth-system columns like
     * remember_token, and foreign-key relationship columns (add those with
     * getStateUsing() against an eager-loaded relation instead).
     */
    protected function isSkippedColumn(string $name): bool
    {
        return in_array($name, ['created_at', 'updated_at', 'deleted_at', 'remember_token'], true)
            || str_ends_with($name, '_id');
    }

    /**
     * Columns that should render as masked, revealable password inputs —
     * the password column itself plus its common companions.
     */
    protected function isPasswordColumn(string $name): bool
    {
        return in_array($name, ['password', 'password_confirmation', 'current_password'], true);
    }

    protected function isIntegerType(string $type): bool
    {
        return str_contains($type, 'int');
    }

    protected function isTemporalType(string $type): bool
    {
        return str_contains($type, 'time') || str_contains($type, 'date');
    }
}
