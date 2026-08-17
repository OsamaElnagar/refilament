<?php

declare(strict_types=1);

namespace Refilament\Refilament\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeResourceCommand extends Command
{
    /**
     * The form field classes referenced by the generated form body, keyed by
     * FQCN - used to emit only the `use` statements the body actually needs.
     *
     * @var array<string, true>
     */
    protected array $usedFormFields = [];

    /**
     * The table column classes referenced by the generated table body, keyed
     * by class name - used to emit only the `use` statements the body needs.
     *
     * @var array<string, true>
     */
    protected array $usedTableColumns = [];

    /**
     * The command name and signature.
     */
    protected $name = 'refilament:make-resource';

    /**
     * The command description.
     */
    protected $description = 'Create a new Refilament resource class and its table/form/infolist classes';

    /**
     * @return array<int, array{0: string, 1: int, 2: string}>
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the resource (e.g. "Post" or "posts").'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['generate', 'G', InputOption::VALUE_NONE, 'Generate columns and fields from the model table.'],
            ['force', 'F', InputOption::VALUE_NONE, 'Overwrite any existing files.'],
            ['model', null, InputOption::VALUE_REQUIRED, 'The fully-qualified model class to base the resource on.'],
            ['model-namespace', null, InputOption::VALUE_REQUIRED, 'The namespace to look for the model in when --model is omitted (default: App\\Models).'],
            ['view', null, InputOption::VALUE_NONE, 'Generate a tailored read-only infolist schema for the view page.'],
            ['soft-deletes', null, InputOption::VALUE_NONE, 'Generate a trashed-records filter (auto-detected from the model\'s SoftDeletes trait).'],
            ['record-title-attribute', null, InputOption::VALUE_REQUIRED, 'The attribute used to title records (auto-detected from a title/name column).'],
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $filesystem): int
    {
        $name = Str::studly($this->argument('name'));
        $className = $name.'Resource';

        $modelNamespace = (string) ($this->option('model-namespace') ?? 'App\\Models');
        /** @var class-string<Model> $model */
        $model = $this->option('model') ?? $modelNamespace.'\\'.$name;

        $resourcesPath = (string) config('refilament.resources.path', app_path('Refilament/Resources'));
        $resourcesNamespace = (string) config('refilament.resources.namespace', 'App\\Refilament\\Resources');

        // Self-contained per-resource directory (mirrors Filament): the plural
        // resource folder holds the thin resource class plus its Schemas/ and
        // Tables/ subdirectories, so each resource is expandable independently
        // (Pages/, RelationManagers/, Actions/, Widgets/ slot in later).
        $plural = Str::pluralStudly($name);
        $basePath = $resourcesPath.'/'.$plural;
        $baseNamespace = $resourcesNamespace.'\\'.$plural;

        $resourceFile = $basePath.'/'.$className.'.php';
        $schemaDir = $basePath.'/Schemas';
        $tableDir = $basePath.'/Tables';
        $pagesDir = $basePath.'/Pages';
        $schemaFile = $schemaDir.'/'.$name.'Form.php';
        $tableFile = $tableDir.'/'.$plural.'Table.php';
        $infolistFile = $schemaDir.'/'.$name.'Infolist.php';

        if (! class_exists($model)) {
            $this->components->error("Model [{$model}] does not exist.");

            return self::FAILURE;
        }

        $generateView = (bool) $this->option('view');
        $usesSoftDeletes = (bool) $this->option('soft-deletes') || $this->modelUsesSoftDeletes($model);
        $recordTitleAttribute = $this->option('record-title-attribute') ?? $this->detectRecordTitleAttribute($model);

        // The generated Pages/ classes (slice 1.10 — docs/ROADMAP.md "1.10
        // Pages/ subdirectory"): one thin class per built-in page, mirroring
        // Filament's per-resource Pages/ layout. The list page carries the
        // default CreateAction header action.
        $pageFiles = [
            'List'.$name.'.php' => $pagesDir.'/List'.$name.'.php',
            'Create'.$name.'.php' => $pagesDir.'/Create'.$name.'.php',
            'Edit'.$name.'.php' => $pagesDir.'/Edit'.$name.'.php',
            'View'.$name.'.php' => $pagesDir.'/View'.$name.'.php',
        ];
        $resourceFqn = $baseNamespace.'\\'.$className;
        $pagesNamespace = $baseNamespace.'\\Pages';

        // The resolved ids for the generated classes — the resource's derived
        // defaults unless a resource of the same id is already registered.
        $tableId = Str::kebab($name);
        $formId = $tableId.'-form';

        $files = $generateView
            ? [$resourceFile, $schemaFile, $infolistFile, $tableFile, ...array_values($pageFiles)]
            : [$resourceFile, $schemaFile, $tableFile, ...array_values($pageFiles)];

        foreach ($files as $file) {
            if ($filesystem->exists($file) && ! $this->option('force')) {
                $this->components->error("{$file} already exists.");

                return self::FAILURE;
            }
        }

        if ($this->option('generate')) {
            [$tableBody, $formBody, $infolistBody] = $this->generateBodies($model);
        } else {
            $tableBody = self::tablePlaceholder();
            $formBody = self::formPlaceholder();
            $infolistBody = self::infolistPlaceholder();
        }

        $fieldImports = $this->renderFieldImports();
        $toggleColumnImport = isset($this->usedTableColumns['ToggleColumn']) ? "\nuse Refilament\\Refilament\\Tables\\Columns\\ToggleColumn;" : '';

        // The stubs embed the bodies one level past the `->columns([` /
        // `->components([` chain lines (12 spaces + 4) — indent every line
        // so the generated files are pint-clean out of the box.
        $tableBody = $this->indent($tableBody, 16);
        $formBody = $this->indent($formBody, 16);
        $infolistBody = $this->indent($infolistBody, 16);

        $filesystem->ensureDirectoryExists($schemaDir);
        $filesystem->ensureDirectoryExists($tableDir);
        $filesystem->ensureDirectoryExists($pagesDir);

        $resourceStub = $filesystem->get(__DIR__.'/../../stubs/resource.stub');
        $schemaStub = $filesystem->get(__DIR__.'/../../stubs/schema.stub');
        $tableStub = $filesystem->get(__DIR__.'/../../stubs/table.stub');
        $infolistStub = $filesystem->get(__DIR__.'/../../stubs/infolist.stub');
        $resourcePageStub = $filesystem->get(__DIR__.'/../../stubs/resource-page.stub');
        $resourceListPageStub = $filesystem->get(__DIR__.'/../../stubs/resource-list-page.stub');

        $infolistFqn = $baseNamespace.'\\Schemas\\'.$name.'Infolist';

        $filesystem->put($resourceFile, str_replace(
            [
                '{{ namespace }}', '{{ class }}',
                '{{ schemaFqn }}', '{{ tableFqn }}', '{{ infolistImport }}',
                '{{ schemaShort }}', '{{ tableShort }}', '{{ infolistMethod }}',
                '{{ modelImport }}', '{{ modelShort }}', '{{ recordTitle }}',
                '{{ pagesNamespace }}', '{{ name }}',
            ],
            [
                $baseNamespace, $className,
                "{$baseNamespace}\\Schemas\\{$name}Form", "{$baseNamespace}\\Tables\\{$plural}Table",
                $generateView ? "\nuse {$infolistFqn};" : '',
                "{$name}Form", "{$plural}Table",
                $generateView ? "\n\n    /**\n     * The read-only infolist shown on the record view page (generated with\n     * --view) - delegated to the standalone class so the read-out can be\n     * reused elsewhere.\n     */\n    public static function infolist(Schema \$schema): Schema\n    {\n        return {$name}Infolist::configure(\$schema);\n    }" : '',
                $model, Str::afterLast($model, '\\'),
                $recordTitleAttribute !== null ? "'{$recordTitleAttribute}'" : 'null',
                $pagesNamespace, $name,
            ],
            $resourceStub,
        ));

        // One thin page class per built-in page, extending the framework's
        // page and declaring its resource — the list page additionally ships
        // the default CreateAction header action (slice 1.10).
        $pageVariables = [
            '{{ namespace }}' => $pagesNamespace,
            '{{ resourceFqn }}' => $resourceFqn,
            '{{ resourceShort }}' => $className,
        ];

        foreach (['List', 'Create', 'Edit', 'View'] as $pageName) {
            $stub = $pageName === 'List' ? $resourceListPageStub : $resourcePageStub;
            $extends = [
                'List' => 'ListRecords',
                'Create' => 'CreateRecord',
                'Edit' => 'EditRecord',
                'View' => 'ViewRecord',
            ][$pageName];

            [$actionImports, $headerActions] = self::pageHeaderActions($pageName);

            $filesystem->put(
                $pageFiles[$pageName.$name.'.php'],
                str_replace(
                    [...array_keys($pageVariables), '{{ class }}', '{{ extends }}', '{{ actionImports }}', '{{ headerActions }}'],
                    [...array_values($pageVariables), $pageName.$name, $extends, $actionImports, $headerActions],
                    $stub,
                ),
            );
        }

        $filesystem->put($schemaFile, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ formId }}', '{{ body }}', '{{ fieldImports }}'],
            ["{$baseNamespace}\\Schemas", "{$name}Form", $formId, $formBody, $fieldImports],
            $schemaStub,
        ));

        if ($generateView) {
            $filesystem->put($infolistFile, str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ body }}'],
                ["{$baseNamespace}\\Schemas", $name.'Infolist', $infolistBody],
                $infolistStub,
            ));
        }

        $filesystem->put($tableFile, str_replace(
            [
                '{{ namespace }}', '{{ class }}', '{{ tableId }}', '{{ body }}',
                '{{ modelImport }}', '{{ modelShort }}', '{{ toggleColumnImport }}', '{{ filtersImport }}', '{{ filtersBody }}',
                '{{ actionImports }}', '{{ recordActions }}',
            ],
            [
                "{$baseNamespace}\\Tables", "{$plural}Table", $tableId, $tableBody,
                $model, Str::afterLast($model, '\\'),
                $toggleColumnImport,
                $usesSoftDeletes ? "\nuse Refilament\\Refilament\\Tables\\TrashedFilter;" : '',
                $usesSoftDeletes ? "\n            ->filters([TrashedFilter::make()])" : '',
                self::tableActionImports(),
                self::tableRecordActions(),
            ],
            $tableStub,
        ));

        $this->components->info("{$className} created at {$resourceFile} with Pages/List{$name}, Create{$name}, Edit{$name} and View{$name}.");

        if ($this->option('generate')) {
            $this->components->info('Generated columns, fields and entries from the model table — customize the generated files before shipping.');
        } else {
            $this->components->info('Define columns, fields and entries in the generated table(), form() and infolist() classes.');
        }

        if ($usesSoftDeletes) {
            $this->components->info('The model uses soft deletes — added a trashed-records filter to the table.');
        }

        if ($generateView) {
            $this->components->info("Generated the read-only {$name}Infolist schema and wired it into infolist().");
        }

        return self::SUCCESS;
    }

    /**
     * Build the table columns, form fields and infolist entries from the
     * model's table columns.
     *
     * @param  class-string<Model>  $model
     * @return array{0: string, 1: string, 2: string}
     */
    protected function generateBodies(string $model): array
    {
        $table = (new $model)->getTable();
        $columns = Schema::getColumns($table);

        $tableColumns = [];
        $formFields = [];
        $infolistEntries = [];

        foreach ($columns as $column) {
            $name = (string) $column['name'];
            $type = (string) $column['type_name'];
            $nullable = (bool) $column['nullable'];
            $label = Str::headline($name);

            if ($name === 'id') {
                $tableColumns[] = "Column::make('id')->label('ID')->sortable(),";
                $infolistEntries[] = "TextEntry::make('id')->label('ID'),";

                continue;
            }

            if ($this->isSkippedColumn($name)) {
                continue;
            }

            if ($this->isBooleanType($type)) {
                $tableColumns[] = "ToggleColumn::make('{$name}')->label('{$label}'),";
                $this->usedTableColumns['ToggleColumn'] = true;
                $formFields[] = $this->trackField('Toggle', $name, $label, $nullable).',';
                $infolistEntries[] = "TextEntry::make('{$name}')->label('{$label}'),";

                continue;
            }

            if ($this->isIntegerType($type)) {
                $tableColumns[] = "Column::make('{$name}')->label('{$label}'),";
                $formFields[] = $this->trackField('TextInput', $name, $label, $nullable).'->integer(),';
                $infolistEntries[] = "TextEntry::make('{$name}')->label('{$label}')->numeric(),";

                continue;
            }

            if ($this->isTemporalType($type)) {
                $tableColumns[] = "Column::make('{$name}')->label('{$label}')->sortable(),";
                $formFields[] = $this->trackField($this->temporalField($type), $name, $label, $nullable).',';
                $infolistEntries[] = "TextEntry::make('{$name}')->label('{$label}'),";

                continue;
            }

            if ($this->isTextType($type)) {
                $tableColumns[] = "Column::make('{$name}')->label('{$label}'),";
                $formFields[] = $this->trackField('Textarea', $name, $label, $nullable).',';
                $infolistEntries[] = "TextEntry::make('{$name}')->label('{$label}'),";

                continue;
            }

            // Strings and anything else land as a text input. Password-named
            // columns become masked, revealable password inputs.
            $length = isset($column['length']) && is_numeric($column['length'])
                ? (int) $column['length']
                : null;

            $tableColumns[] = "Column::make('{$name}')->label('{$label}'),";
            $formFields[] = $this->trackField('TextInput', $name, $label, $nullable)
                .($this->isPasswordColumn($name) ? '->password()->revealable()' : '')
                .($length !== null ? "->maxLength({$length})" : '')
                .',';
            $infolistEntries[] = "TextEntry::make('{$name}')->label('{$label}'),";
        }

        if ($tableColumns === []) {
            $tableColumns[] = self::tablePlaceholder();
        }

        if ($formFields === []) {
            $formFields[] = self::formPlaceholder();
        }

        if ($infolistEntries === []) {
            $infolistEntries[] = self::infolistPlaceholder();
        }

        return [implode("\n", $tableColumns), implode("\n", $formFields), implode("\n", $infolistEntries)];
    }

    protected static function tablePlaceholder(): string
    {
        return '// TODO: define columns — e.g.';
    }

    protected static function formPlaceholder(): string
    {
        return '// TODO: define fields — e.g.';
    }

    protected static function infolistPlaceholder(): string
    {
        return '// TODO: define entries — e.g.';
    }

    /**
     * The `use` statements the generated table needs for its default per-row
     * record actions, alphabetized ahead of the Table imports so the output
     * stays pint-clean out of the box.
     */
    protected static function tableActionImports(): string
    {
        return "use Refilament\\Refilament\\Actions\\DeleteAction;\nuse Refilament\\Refilament\\Actions\\EditAction;\n";
    }

    /**
     * The default per-row record actions on a generated table — edit and
     * delete, mirroring Filament's generated resources.
     */
    protected static function tableRecordActions(): string
    {
        return "\n            ->recordActions([\n                EditAction::make(),\n                DeleteAction::make(),\n            ])";
    }

    /**
     * The action imports and header-actions body for a generated page. Record
     * pages ship the framework's built-in navigation actions by default: the
     * edit page carries view + delete, the view page edit + delete. The list
     * and create pages ship none (the list page stub declares its own
     * CreateAction).
     *
     * @return array{0: string, 1: string} the import block and the header-actions body
     */
    protected static function pageHeaderActions(string $pageName): array
    {
        $specs = [
            'Edit' => [['Action', 'DeleteAction', 'ViewAction'], "ViewAction::make(),\n            DeleteAction::make()"],
            'View' => [['Action', 'DeleteAction', 'EditAction'], "EditAction::make(),\n            DeleteAction::make()"],
        ];

        if (! isset($specs[$pageName])) {
            return ['', ''];
        }

        [$imports, $actions] = $specs[$pageName];

        $importBlock = implode("\n", array_map(
            static fn (string $name): string => "use Refilament\\Refilament\\Actions\\{$name};",
            $imports,
        ))."\n";

        $headerActions = "\n    /**\n     * Header actions — the built-in navigation actions for this record page.\n     *\n     * @return array<int, Action>\n     */\n    protected static function getHeaderActions(string \$resource): array\n    {\n        return [\n            {$actions}\n        ];\n    }\n";

        return [$importBlock, $headerActions];
    }

    /**
     * Record a used form field class and return the field's opening call, so
     * the schema stub only imports what the body references.
     */
    protected function trackField(string $field, string $name, string $label, bool $nullable): string
    {
        $this->usedFormFields["Refilament\\Refilament\\Schemas\\Components\\{$field}"] = true;

        return "{$field}::make('{$name}')->label('{$label}')".($nullable ? '' : '->required()');
    }

    /**
     * Render the `use` statements the generated form body needs, alphabetized
     * ahead of the Schema import for pint-clean output.
     */
    protected function renderFieldImports(): string
    {
        $fqns = array_keys($this->usedFormFields);
        natcasesort($fqns);

        if ($fqns === []) {
            return '';
        }

        return implode('', array_map(
            static fn (string $fqn): string => "use {$fqn};\n",
            $fqns,
        ));
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
     * The first title/name-style column in the model's table, used as the
     * resource's record title attribute — mirrors Filament's choice of a
     * human-friendly display attribute for breadcrumbs and URLs.
     *
     * @param  class-string<Model>  $model
     */
    protected function detectRecordTitleAttribute(string $model): ?string
    {
        $names = array_column(Schema::getColumns((new $model)->getTable()), 'name');

        foreach (['title', 'name'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether the model uses the SoftDeletes trait.
     *
     * @param  class-string<Model>  $model
     */
    protected function modelUsesSoftDeletes(string $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
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

    protected function isBooleanType(string $type): bool
    {
        return str_contains($type, 'bool') || str_contains($type, 'tinyint');
    }

    protected function isIntegerType(string $type): bool
    {
        return str_contains($type, 'int');
    }

    protected function isTemporalType(string $type): bool
    {
        return str_contains($type, 'time') || str_contains($type, 'date');
    }

    protected function isTextType(string $type): bool
    {
        return str_contains($type, 'text');
    }

    /**
     * The field class that best matches a temporal column type.
     */
    protected function temporalField(string $type): string
    {
        if (str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
            return 'DateTimePicker';
        }

        return str_contains($type, 'time') ? 'TimePicker' : 'DatePicker';
    }
}
