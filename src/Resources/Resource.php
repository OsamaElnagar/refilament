<?php

declare(strict_types=1);

namespace Refilament\Refilament\Resources;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
use Refilament\Refilament\GlobalSearch\GlobalSearchResult;
use Refilament\Refilament\Resources\Pages\CreateRecord;
use Refilament\Refilament\Resources\Pages\EditRecord;
use Refilament\Refilament\Resources\Pages\ListRecords;
use Refilament\Refilament\Resources\Pages\PageRegistration;
use Refilament\Refilament\Resources\Pages\ViewRecord;
use Refilament\Refilament\Resources\RelationManagers\RelationManager;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Tables\Table;

/**
 * A resource bundles an Eloquent model with the table definition the typed
 * index endpoint serves and the form schema the typed submit endpoint
 * validates (docs/ARCHITECTURE.md, "Resources").
 *
 * Resource classes are usually generated with `refilament:make-resource`
 * and discovered automatically from the configured resources directory,
 * mirroring Filament's panel resource discovery (`isDiscovered()`). The
 * ids the endpoints serve under derive from the class name unless a
 * `$tableId` / `$formId` property overrides them.
 */
abstract class Resource
{
    /**
     * The Eloquent model class this resource manages.
     *
     * @var class-string|null
     */
    protected static ?string $model = null;

    /**
     * Override to change the id the typed table index endpoint serves this
     * resource's table under (derives from the class name otherwise).
     */
    protected static ?string $tableId = null;

    /**
     * Override to change the id the typed submit endpoint addresses this
     * resource's form by (derives from the class name otherwise).
     */
    protected static ?string $formId = null;

    protected static bool $isDiscovered = true;

    /**
     * The label shown in the panel sidebar for this resource (slice 1.9).
     * Falls back to the plural, headlined model name (e.g. "Users").
     */
    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = null;

    /**
     * Whether this resource appears in the panel sidebar at all. Hiding it
     * only removes it from the sidebar — its pages stay reachable by URL.
     */
    protected static bool $shouldRegisterNavigation = true;

    /**
     * The model attribute that names a record in global search results — the
     * headline shown for each hit. When unset, global search falls back to the
     * resource's first searchable table column (slice 3.5). Mirrors Filament's
     * `$recordTitleAttribute`.
     */
    protected static ?string $recordTitleAttribute = null;

    /**
     * Whether this resource takes part in the panel's global search at all.
     */
    protected static bool $isGloballySearchable = true;

    /**
     * The maximum number of records a single resource may contribute to a
     * global search result set.
     */
    protected static int $globalSearchResultsLimit = 50;

    /**
     * Ordering weight within global search — lower sorts first. Mirrors
     * Filament's `getGlobalSearchSort()`.
     */
    protected static ?int $globalSearchSort = null;

    /**
     * The Eloquent model this resource manages.
     *
     * @return class-string
     */
    public static function getModel(): string
    {
        if (static::$model === null) {
            throw new LogicException('Resource ['.static::class.'] must define a [$model] property.');
        }

        return static::$model;
    }

    /**
     * The id the typed table index endpoint serves this resource's table
     * under (PostResource → "post", or the `$tableId` override).
     */
    public static function getTableId(): string
    {
        return static::$tableId ?? Str::kebab(Str::beforeLast(class_basename(static::class), 'Resource'));
    }

    /**
     * The id the typed submit endpoint addresses this resource's form by
     * (PostResource → "post-form", or the `$formId` override).
     */
    public static function getFormId(): string
    {
        return static::$formId ?? Str::kebab(Str::beforeLast(class_basename(static::class), 'Resource')).'-form';
    }

    public static function isDiscovered(): bool
    {
        return static::$isDiscovered;
    }

    /**
     * The table definition served by the typed index endpoint
     * (docs/CONTRACT.md, "Tables").
     */
    abstract public static function table(Table $table): Table;

    /**
     * The form schema validated by the typed submit endpoint
     * (docs/CONTRACT.md, "Form submission").
     */
    abstract public static function form(Schema $schema): Schema;

    /**
     * The read-only infolist shown on the record view page (slice 3.3). By
     * default it is empty, so the view page falls back to rendering the
     * table's columns; override to compose a tailored read-out:
     *
     *     public static function infolist(Schema $schema): Schema
     *     {
     *         return $schema->components([
     *             TextEntry::make('status')->badge()->color(...),
     *             TextEntry::make('views')->numeric(),
     *         ]);
     *     }
     *
     * Mirrors Filament's `infolist()`. The schema's `record()` is bound to
     * the viewed record before serialization, so entries resolve their values.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * Initial values for the create form, keyed by field name, taken from
     * the form fields' `default()`s (Schema::initialData()). Fields without
     * a default carry null.
     *
     * @return array<string, int|string|bool|null>
     */
    public static function formData(): array
    {
        return static::form(new Schema)->initialData();
    }

    /**
     * The relation managers listed under this resource (slice 1.8 —
     * docs/CONTRACT.md, "Relations"). Each hosts one to-many relationship on
     * the resource's model; Refilament registers every returned class under
     * the resource's table id so the scoped relation endpoint can resolve it
     * by relationship name. Override to add relations:
     *
     *     public static function getRelations(): array
     *     {
     *         return [
     *             CommentsRelationManager::class,
     *         ];
     *     }
     *
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * The page map for this resource (slice 1.6 — docs/ROADMAP.md "1.6 Page
     * system"). Every entry is a named page slot (index/create/edit/view
     * plus any custom pages) whose page is carried by a PageRegistration and
     * passes through the typed record pages. The package registers one route
     * per entry at boot — the built-ins render the generic list/create/edit/
     * view components. Override to add custom pages:
     *
     *     public static function getPages(): array
     *     {
     *         return [
     *             ...parent::getPages(),
     *             'stats' => PostStatsPage::route('/stats'),
     *         ];
     *     }
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'create' => CreateRecord::route('/create'),
            'edit' => EditRecord::route('/{record}/edit'),
            'view' => ViewRecord::route('/{record}'),
        ];
    }

    /**
     * The panel-sidebar label (slice 1.9) — the `$navigationLabel` override,
     * else the plural, headlined model name ("User" → "Users").
     */
    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel
            ?? Str::headline(Str::plural(class_basename(static::getModel())));
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }
    {
        return static::$navigationGroup;
    }

    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }

    /**
     * Where the resource's nav item links — its list page.
     */
    public static function getNavigationUrl(): string
    {
        return '/refilament/'.static::getTableId();
    }

    /**
     * Configure the resource's sidebar surface: `navigationLabel()`,
     * `navigationIcon()`, `navigationGroup()`, `navigationSort()` and
     * `shouldRegisterNavigation()`. Mirrors Filament's static setters.
     */
    public static function navigationLabel(?string $label): void
    {
        static::$navigationLabel = $label;
    }

    public static function navigationIcon(?string $icon): void
    {
        static::$navigationIcon = $icon;
    }

    public static function navigationGroup(?string $group): void
    {
        static::$navigationGroup = $group;
    }

    public static function navigationSort(?int $sort): void
    {
        static::$navigationSort = $sort;
    }

    /**
     * The model attribute that headlines a record in global search (slice
     * 3.5) — the `$recordTitleAttribute` override if set.
     */
    public static function getRecordTitleAttribute(): ?string
    {
        return static::$recordTitleAttribute;
    }

    /**
     * Whether this resource takes part in the panel's global search: it is
     * globally searchable, has at least one searchable attribute, and the
     * current user can access it. `canAccess()` defaults true (authorization
     * lands in slice 4.1).
     */
    public static function canGloballySearch(): bool
    {
        return static::$isGloballySearchable
            && static::getGloballySearchableAttributes() !== []
            && static::canAccess();
    }

    /**
     * Whether the current user may access this resource (slice 4.1). Defaults
     * true — the authorization slice replaces this with a policy-aware check.
     */
    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * The model attributes global search matches against (slice 3.5). Defaults
     * to the record title attribute when set, else the resource table's
     * searchable columns. Both mirror Filament's default of the record title
     * attribute and keep configuration in the table where our search already
     * lives.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        if (static::$recordTitleAttribute !== null) {
            return [static::$recordTitleAttribute];
        }

        return array_values(array_map(
            static fn ($column): string => (string) $column->getName(),
            (static::table(new \Refilament\Refilament\Tables\Table))->getSearchableColumns(),
        ));
    }

    /**
     * The headline shown for a record in global search results (slice 3.5).
     * Defaults to the record title attribute's value, then the record's first
     * searchable attribute, then the model's string representation.
     */
    public static function getGlobalSearchResultTitle(mixed $record): string
    {
        foreach (static::getGloballySearchableAttributes() as $attribute) {
            if (isset($record->{$attribute}) && ! blank($record->{$attribute})) {
                return (string) $record->{$attribute};
            }
        }

        return (string) $record;
    }

    /**
     * Where a global search result links (slice 3.5) — the record's built-in
     * view page by default.
     */
    public static function getGlobalSearchResultUrl(mixed $record): ?string
    {
        return route('refilament.resource.view', [
            'resource' => static::getTableId(),
            'record' => $record->getKey(),
        ]);
    }
    public static function getGlobalSearchResultDetails(mixed $record): array
    {
        return [];
    }

    /**
     * The records matching the given query across every searchable attribute,
     * capped at the resource's limit and wrapped in GlobalSearchResult
     * objects (slice 3.5). OR-ed LIKE clauses mirror the table's own global
     * search, and modifyGlobalSearchQuery() lets a resource narrow or re-rank.
     *
     * @return Collection<int, GlobalSearchResult>
     */
    public static function getGlobalSearchResults(string $query): Collection
    {
        $builder = static::getGlobalSearchEloquentQuery();

        static::applyGlobalSearchConstraint($builder, $query);

        static::modifyGlobalSearchQuery($builder, $query);

        return $builder
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(static::wrapGlobalSearchResult(...))
            ->filter();
    }

    /**
     * The base query global search starts from (slice 3.5). Defaults to the
     * resource model's query — override to scope (e.g. by tenant or soft deletes).
     */
    public static function getGlobalSearchEloquentQuery(): EloquentBuilder
    {
        return static::getModel()::query();
    }

    /**
     * Hook for a resource to narrow or re-rank the search query before results
     * are collected (slice 3.5). A no-op by default.
     */
    public static function modifyGlobalSearchQuery(EloquentBuilder $query, string $search): void {}

    public static function getGlobalSearchResultsLimit(): int
    {
        return static::$globalSearchResultsLimit;
    }

    public static function getGlobalSearchSort(): ?int
    {
        return static::$globalSearchSort;
    }

    public static function globalSearchSort(?int $sort): void
    {
        static::$globalSearchSort = $sort;
    }

    /**
     * OR-ed LIKE clauses across every searchable attribute, narrowed to the
     * given query term.
     */
    protected static function applyGlobalSearchConstraint(EloquentBuilder $query, string $search): void
    {
        if (trim($search) === '') {
            return;
        }

        $attributes = static::getGloballySearchableAttributes();

        if ($attributes === []) {
            throw new LogicException('Cannot globally search a resource with no searchable attributes.');
        }

        $query->where(function (EloquentBuilder $builder) use ($search, $attributes): void {
            foreach ($attributes as $attribute) {
                $builder->orWhere($attribute, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * Wrap a matched record into a GlobalSearchResult (slice 3.5). Returns
     * null when the record resolves no URL, so it is dropped from the result.
     */
    protected static function wrapGlobalSearchResult(mixed $record): ?GlobalSearchResult
    {
        $url = static::getGlobalSearchResultUrl($record);

        if (blank($url)) {
            return null;
        }

        return new GlobalSearchResult(
            title: static::getGlobalSearchResultTitle($record),
            url: $url,
            details: static::getGlobalSearchResultDetails($record),
        );
    }
}
