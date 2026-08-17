<?php

declare(strict_types=1);

namespace Refilament\Refilament\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Refilament\Refilament\Clusters;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Schemas\Components\TextInput;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;

/**
 * Base page (slice 1.6 — docs/ROADMAP.md "1.6 Page system").
 *
 * A page knows its Inertia component and how to build its payload. Resource
 * pages (the built-in list/create/edit/view plus custom pages) extend
 * Resources\Pages\Page, which adds the resource linkage and the route()
 * registration factory; standalone pages extend this base and are wired by
 * the panel shell (slice 1.9 ->pages([...])) — those register their route via
 * getRoutePath()/getInertiaComponent() and are served by the single
 * PanelPageController. Mirrors Filament's Page surface where it is pure data —
 * navigation metadata feeds the panel shell, never the page payload.
 */
abstract class Page
{
    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?int $navigationSort = null;

    protected static ?string $navigationGroup = null;

    /**
     * The URL path this page registers under, relative to its resource segment
     * (resource pages) or the panel root (standalone pages). Resource pages'
     * route() factory uses it; standalone panel pages are served at their
     * getSlug() instead, so this defaults empty.
     */
    protected static string $routePath = '';

    /**
     * Whether this page appears in the panel sidebar. Only pages that opt in
     * (and are not a built-in resource page already surfaced by their
     * resource's nav item) register a nav entry — mirroring Filament, where
     * most pages do not appear in the sidebar. Resource pages default to
     * false; custom pages set it to true to surface themselves.
     */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * Whether the browser prompts before navigating away while the page's
     * form holds unsaved changes (the page-forms slice — mirrors Filament's
     * HasUnsavedDataChangesAlert trait's `$hasUnsavedDataChangesAlert`). The
     * client-side page-form component tracks dirty state and intercepts
     * Inertia visits; a confirm dialog asks before leaving.
     */
    protected static bool $hasUnsavedDataChangesAlert = false;

    /**
     * The Eloquent model this page's form edits — the singular-resource
     * slice (Filament's documented "singular resource" pattern: one record,
     * auto-created on first save). Declaring it wires the whole pattern:
     * getFormData() loads the record's values for the form's fields,
     * getFormSchema() auto-registers a create-or-update submit handler
     * (create when no record exists yet, update it afterwards) and makes the
     * validation rules ignore the record's own unique values. Override
     * getRecordQuery() to scope which record (e.g.
     * `->where('is_homepage', true)`).
     *
     * @var class-string|null
     */
    protected static ?string $model = null;

    /**
     * The cluster this page belongs to (the page-clusters slice) — declaring
     * it groups the page under the cluster's sidebar entry (sub-navigation),
     * prefixes a standalone page's slug with the cluster's, and adds the
     * cluster crumb to the page's breadcrumbs. Mirrors Filament's
     * `protected static ?string $cluster`.
     *
     * @var class-string<Clusters\Cluster>|null
     */
    protected static ?string $cluster = null;

    public static function getTitle(): string
    {
        return static::$title ?? static::getNavigationLabel();
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel
            ?? static::$title
            ?? Str::headline(class_basename(static::class));
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }

    /**
     * The URL slug for this standalone panel page (slice 1.9 ->pages()),
     * relative to the panel root. Defaults to the kebab-cased plural of the
     * class basename, mirroring Filament's `getDefaultSlug()`, e.g. an
     * `AboutPage` class serves `/refilament/about-page`. The panel reads it
     * when auto-registering the page's route and its sidebar item. (The
     * cluster prefix is applied by getSlugPath(), not here — a consumer's
     * override of getSlug() stays the bare segment, mirroring Filament,
     * where `Cluster::prependClusterSlug()` applies at route/nav build
     * time.)
     */
    public static function getSlug(): string
    {
        return static::getDefaultSlug();
    }

    /**
     * The slug segment derived from the class basename alone — `getSlug()`
     * defaults to it, and the clustered page route registers its `{page}`
     * gate from it.
     */
    public static function getDefaultSlug(): string
    {
        return (string) Str::slug(Str::kebab(class_basename(static::class)));
    }

    /**
     * The page's URL path relative to the panel root (the page-clusters
     * slice) — a clustered page serves at `/{cluster-slug}/{slug}`,
     * mirroring Filament's `Cluster::prependClusterSlug()`; an unclustered
     * page at `/{slug}`. The panel uses this when building the page's route
     * URL, its sidebar item and the cluster redirect.
     */
    public static function getSlugPath(): string
    {
        $slug = static::getSlug();

        if (static::getCluster() !== null) {
            return static::getCluster()::getSlug().'/'.$slug;
        }

        return $slug;
    }

    /**
     * The cluster this page belongs to (the page-clusters slice), if any.
     *
     * @return class-string<Clusters\Cluster>|null
     */
    public static function getCluster(): ?string
    {
        return static::$cluster;
    }

    /**
     * Whether this page belongs to a cluster.
     */
    public static function isClustered(): bool
    {
        return static::getCluster() !== null;
    }

    /**
     * The cluster's own URL (its redirect route), or null for an unclustered
     * page — the link the cluster crumb and the cluster's sidebar item point
     * at.
     */
    public static function getClusterUrl(): ?string
    {
        $cluster = static::getCluster();

        if ($cluster === null) {
            return null;
        }

        return route('refilament.cluster', ['cluster' => $cluster::getSlug()]);
    }

    /**
     * Prepends this page's cluster crumb to a breadcrumb chain (the
     * page-clusters slice) — mirroring Filament's
     * `Cluster::unshiftClusterBreadcrumbs()`: `[clusterUrl => clusterLabel, ...$breadcrumbs]`.
     * The cluster crumb never duplicates itself, and unclustered pages pass
     * the chain through untouched. A caller may pass an explicit cluster
     * (resource pages resolve theirs from the URL segment, since built-in
     * pages used directly in a getPages() map declare no $resource).
     *
     * @param  array<int, array{label: string, url?: string}>  $breadcrumbs
     * @return array<int, array{label: string, url?: string}>
     */
    public static function unshiftClusterBreadcrumbs(array $breadcrumbs, ?string $cluster = null): array
    {
        $cluster ??= static::getCluster();

        if ($cluster === null || $breadcrumbs === []) {
            return $breadcrumbs;
        }

        $crumb = ['label' => $cluster::getClusterBreadcrumb() ?? $cluster::getNavigationLabel(), 'url' => static::getClusterUrl() ?? ''];

        if (($breadcrumbs[0]['label'] ?? null) === $crumb['label']) {
            return $breadcrumbs;
        }

        array_unshift($breadcrumbs, $crumb);

        return $breadcrumbs;
    }

    /**
     * The Inertia props for this standalone page (slice 1.9 ->pages()),
     * computed server-side per request. Standalone pages override this to add
     * their own data. Named distinctly from the resource pages' getViewData()
     * so the two page families can coexist on this shared base — the panel
     * page controller renders getInertiaComponent() with these props.
     *
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [];
    }

    /**
     * This page's breadcrumbs (slice 1.11), in display order — serialized as
     * a `breadcrumbs` prop when non-empty. Standalone pages default to none
     * (they usually sit at the top of the panel), mirroring Filament, where a
     * non-resource page's breadcrumbs are empty unless it overrides
     * getBreadcrumbs(). Override to add crumbs (each `{ label, url? }`, the
     * last entry the current page, never a link).
     *
     * @return array<int, array{label: string, url?: string}>
     */
    public static function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * The id the page's form schema is registered under and the client uses
     * to address the typed submit / validate endpoints (the page-forms
     * slice). Derived from the full class name, so two pages sharing a class
     * basename in different namespaces never collide. Override for a stable,
     * human-readable id (e.g. 'site-settings').
     */
    public static function getFormId(): string
    {
        return 'page-'.Str::kebab(str_replace(['\\', '/'], '-', static::class));
    }

    /**
     * The page's form schema — the single override point for a page that
     * hosts a form (mirroring Filament's `InteractsWithForms::form()`). The
     * schema's submitUsing() handler persists the validated data; its
     * fields' rules validate server-side on submit. The serializer sets the
     * schema's id to getFormId() automatically, so consumers never call
     * `->id()` here. Must return **fresh instances per call** (the
     * `new Schema` idiom) — the schema resolver rebuilds it per request, so
     * state must never be cached in a static property.
     */
    protected static function form(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * The page's form as a live instance — null when the page declares no
     * form (its `form()` adds no components), so form-less pages never pay
     * for the pipeline. The public face of the (protected) form() override
     * point: the page serializer and Refilament's resolver registration both
     * go through here.
     */
    /**
     * Whether the page declares a form — builds the schema WITHOUT the
     * singular-resource auto-wire (no DB query), so route registration at
     * boot stays database-free even on a fresh install. The resolver
     * registration and payload serialization use getFormSchema() (which
     * auto-wires per request); this is the cheap gate for boot-time wiring.
     */
    public static function hasFormSchema(): bool
    {
        return static::form(new Schema)->getComponents() !== [];
    }

    public static function getFormSchema(): ?Schema
    {
        $schema = static::form(new Schema);

        if ($schema->getComponents() === []) {
            return null;
        }

        // The singular-resource auto save (Filament's documented pattern): a
        // page declaring `$model` without its own submitUsing() gets the
        // create-or-update handler — create the record on the first submit
        // (there is no record yet), update it on every later one. A consumer's
        // explicit submitUsing() always wins. The record also feeds the
        // unique-rule ignore (its own values never fail `unique:`), and a
        // sensible default success message ships when none was declared.
        if (static::getModel() !== null && $schema->getSubmitHandler() === null) {
            $record = static::getRecord();

            $schema
                ->submitUsing(static function (array $data): void {
                    $record = static::getRecord();
                    $model = static::getModel();

                    if ($record === null) {
                        $model::create($data);
                    } else {
                        $record->update($data);
                    }
                })
                ->ignoreCurrentRecord($record?->getKey() !== null ? (string) $record->getKey() : null);

            if ($schema->getSuccessMessage() === null) {
                $schema->successMessage('Saved.');
            }
        }

        return $schema;
    }

    /**
     * The form's starting values, keyed by field name (mirroring Filament's
     * `$data` property filled in `mount()`). Defaults to the fields'
     * `default()`s. A page declaring `$model` (the singular-resource slice)
     * overlays the record's attribute values for the declared fields —
     * Filament's `$this->form->fill($this->getRecord()?->attributesToArray())`
     * — so the form opens holding the record, and opens empty (defaults
     * only) when no record exists yet. A record-scoped custom page (the
     * record-pages slice — `/{record}/manage`) passes its URL record
     * instead, so the form opens pre-filled from the record it edits.
     * Override for full control (e.g. a page bound to the authenticated
     * user).
     *
     * @return array<string, mixed>
     */
    public static function getFormData(?Model $record = null): array
    {
        $data = static::getFormSchema()?->initialData() ?? [];

        $record ??= static::getModel() !== null ? static::getRecord() : null;

        if ($record !== null) {
            foreach (array_keys($data) as $name) {
                $data[$name] = $record->getAttribute($name);
            }
        }

        return $data;
    }

    /**
     * The label of the page form's submit button (mirrors Filament's
     * `getSubmitFormActionLabel()`, which defaults to 'Save').
     */
    public static function getFormSubmitLabel(): string
    {
        return 'Save';
    }

    public static function hasUnsavedDataChangesAlert(): bool
    {
        return static::$hasUnsavedDataChangesAlert;
    }

    /**
     * The model this page's form edits (the singular-resource slice), if
     * any. Declaring `$model` wires the auto create-or-update save and the
     * record-loaded starting values.
     *
     * @return class-string|null
     */
    public static function getModel(): ?string
    {
        return static::$model;
    }

    /**
     * The query the singular record resolves through (the singular-resource
     * slice) — Filament's `getRecord()` idiom: the first matching row is the
     * record this page edits. Defaults to the model's query; override to
     * scope it (e.g. `->where('is_homepage', true)` on a CMS homepage page,
     * or a tenant scoping).
     *
     * @return Builder<Model>
     */
    public static function getRecordQuery(): Builder
    {
        $model = static::getModel();

        if ($model === null) {
            throw new LogicException('Page ['.static::class.'] must declare a [$model] property to resolve its record.');
        }

        return $model::query();
    }

    /**
     * The singular record this page edits (the singular-resource slice) —
     * the first row of getRecordQuery(), or null when none exists yet (the
     * auto-create-on-first-save case: the form starts empty, and the first
     * submit creates the record).
     */
    public static function getRecord(): ?Model
    {
        return static::getRecordQuery()->first();
    }

    /**
     * The id the page's table is registered under and the client uses to
     * address the typed table endpoint (the pages-as-tables slice). Derived
     * from the full class name, so two pages sharing a class basename in
     * different namespaces never collide — the same derivation as
     * getFormId(). Override for a stable, human-readable id.
     */
    public static function getTableId(): string
    {
        return 'page-'.Str::kebab(str_replace(['\\', '/'], '-', static::class));
    }

    /**
     * The page's table — the single override point for a page that hosts a
     * table (mirroring Filament's `InteractsWithTable::table()`); the
     * report/dashboard idiom. The serializer sets the table's id to
     * getTableId() automatically, so consumers never call `->id()` here.
     * Must return **fresh instances per call** (the `new Table` idiom) — the
     * table resolver rebuilds it per request (pagination, sorting, search
     * and filter closures re-run), so state must never be cached in a static
     * property.
     */
    protected static function table(Table $table): Table
    {
        return $table;
    }

    /**
     * The page's table as a live instance — null when the page declares no
     * table (its `table()` adds no columns), so table-less pages never pay
     * for the pipeline. The public face of the (protected) table() override
     * point: the page serializer and Refilament's resolver registration both
     * go through here.
     */
    public static function getTable(): ?Table
    {
        $table = static::table(new Table);

        return $table->getColumns() === [] ? null : $table;
    }

    /**
     * Serialize the page's table into the page payload (the pages-as-tables
     * slice). The payload is exactly what a resource list page ships — the
     * table definition plus the first page of rows — so the generic
     * refilament/page-table component renders it with the full table
     * machinery (pagination, sorting, search, filters, record + bulk actions
     * through the typed table endpoints). `tableTitle` carries the page's
     * own title for the heading. Returns an empty array for a page that
     * declares no table — the common case ships no extra keys.
     *
     * @return array<string, mixed>
     */
    public static function serializePageTable(): array
    {
        $table = static::getTable();

        if ($table === null) {
            return [];
        }

        $table->id(static::getTableId());

        return [
            ...$table->toPayload(),
            'tableTitle' => static::getTitle(),
        ];
    }

    /**
     * Serialize the page's form into the page payload (the page-forms
     * slice). The `id`, `contract`, `schema`, `data` and `errors` keys mirror
     * the resource create/edit pages' payload shape, so the generic
     * refilament/page-form component renders it like any other form; the
     * page-form-only keys (`formTitle`, `formSubmitLabel`,
     * `hasUnsavedDataChangesAlert`) drive its heading and dirty-state guard.
     * Returns an empty array for a page that declares no form — the common
     * case ships no extra keys.
     *
     * On a record-scoped custom page (the record-pages slice — a `$record`
     * is passed) the form edits the URL record: the data pre-fills from it
     * (password fields always '' — the stored hash never leaves the server),
     * the `record` key forwards it to the client, and `submitUrl` points at
     * the record-bound submit endpoint so the save updates that record with
     * its unique rules ignoring it.
     *
     * @return array<string, mixed>
     */
    public static function serializePageForm(?Model $record = null, ?string $resource = null, ?string $pageName = null): array
    {
        $schema = static::getFormSchema();

        if ($schema === null) {
            return [];
        }

        $schema->id(static::getFormId());

        if ($record !== null && $resource !== null && $pageName !== null) {
            return [
                ...$schema->toArray(),
                'data' => static::serializeRecordFormData($schema, $record),
                'errors' => [],
                'record' => $record->getKey(),
                'submitUrl' => route('refilament.resource.page-form', [
                    'resource' => $resource,
                    'page' => $pageName,
                    'record' => $record->getKey(),
                ]),
                'formTitle' => static::getTitle(),
                'formSubmitLabel' => static::getFormSubmitLabel(),
                'hasUnsavedDataChangesAlert' => static::hasUnsavedDataChangesAlert(),
            ];
        }

        return [
            ...$schema->toArray(),
            'data' => static::getFormData(),
            'errors' => [],
            'formTitle' => static::getTitle(),
            'formSubmitLabel' => static::getFormSubmitLabel(),
            'hasUnsavedDataChangesAlert' => static::hasUnsavedDataChangesAlert(),
        ];
    }

    /**
     * One record's form-field values for the page-form payload — password-
     * typed fields always '' (the stored hash is never serialized back to
     * the client), everything else the record's attribute. The record-scoped
     * counterpart to getFormData() (which resolves the singular record
     * itself); record pages hand their URL record in directly.
     *
     * @return array<string, mixed>
     */
    protected static function serializeRecordFormData(Schema $schema, Model $record): array
    {
        $data = [];

        foreach ($schema->getComponentsRecursively() as $component) {
            $name = $component->getName();

            if ($name === null) {
                continue;
            }

            $data[$name] = $component instanceof TextInput && $component->isPassword()
                ? ''
                : $record->getAttribute($name);
        }

        return $data;
    }

    /**
     * The id the page's infolist is shipped under — derived from the full
     * class name like getFormId(), so two pages sharing a class basename in
     * different namespaces never collide. Override for a stable,
     * human-readable id. (The read-only infolist needs no typed endpoint —
     * entries resolve server-side at payload time — so the id is currently
     * payload metadata only.)
     */
    public static function getInfolistId(): string
    {
        return 'page-'.Str::kebab(str_replace(['\\', '/'], '-', static::class));
    }

    /**
     * The page's read-only infolist — the single override point for a page
     * that displays a record (mirroring Filament's `InteractsWithInfolists`
     * on pages; the record-pages slice `/{record}/manage` idiom). Entries
     * resolve their values from the record the serializer binds. Must return
     * **fresh instances per call** (the `new Schema` idiom) — the schema is
     * rebuilt per request, so state must never be cached in a static
     * property.
     */
    protected static function infolist(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * The page's infolist as a live instance — null when the page declares
     * no infolist (its `infolist()` adds no components), so infolist-less
     * pages never pay for the pipeline. The public face of the (protected)
     * infolist() override point: the page serializer goes through here.
     */
    public static function getInfolist(): ?Schema
    {
        $schema = static::infolist(new Schema);

        return $schema->getComponents() === [] ? null : $schema;
    }

    /**
     * The record the page's infolist reads when no record was passed in — a
     * standalone page hosting an infolist (e.g. a read-only profile page)
     * overrides this to return the record it displays (say, the
     * authenticated user). Record-scoped pages never hit it: the payload
     * passes the URL record explicitly.
     */
    protected static function getInfolistRecord(): ?Model
    {
        return null;
    }

    /**
     * Serialize the page's infolist into the page payload (the page-
     * infolists slice). The `schema` key mirrors the resource view page's
     * infolist payload shape (the read-only renderer walks it), and
     * `infolistTitle` carries the page's own title for the heading. The
     * entries resolve against the record the page reads — the URL record on
     * a record-scoped `/{record}/manage` page, the page's own
     * getInfolistRecord() on a standalone page — via `Schema::record()` (the
     * same binding the resource view uses), so a manage page reads the
     * record it manages with zero consumer code beyond declaring the
     * entries. Returns an empty array for a page that declares no infolist
     * — the common case ships no extra keys.
     *
     * @return array<string, mixed>
     */
    public static function serializePageInfolist(?Model $record = null): array
    {
        $infolist = static::getInfolist();

        if ($infolist === null) {
            return [];
        }

        $record ??= static::getInfolistRecord();

        if ($record !== null) {
            $infolist->record($record);
        }

        return [
            'infolistId' => static::getInfolistId(),
            'schema' => $infolist->toArray()['schema'],
            'infolistTitle' => static::getTitle(),
        ];
    }

    /**
     * The Inertia page component this page renders — resolved through the
     * app's ./pages glob (docs/CONTRACT.md, "Pages").
     */
    abstract public static function getInertiaComponent(): string;
}
