<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LogicException;
use Refilament\Refilament\Tables\Summarizers\Summarizer;

/**
 * Read-only table (slice 6).
 *
 * Columns are pure data; rows come from an Eloquent query, paginated, sorted
 * and filtered server-side through the typed table index endpoint
 * (docs/CONTRACT.md, "Tables"). Row actions run through the typed action
 * endpoint; header actions (slice 1.1) render beside the table and may open
 * modal forms (docs/CONTRACT.md, "Modal actions").
 */
class Table
{
    protected ?string $id = null;

    protected ?string $heading = null;

    /** @var array<int, Column> */
    protected array $columns = [];

    /** @var array<int, SelectFilter|TextFilter|TrashedFilter> */
    protected array $filters = [];

    /** @var array<int, Action> */
    protected array $actions = [];

    /** @var array<int, Action> */
    protected array $headerActions = [];

    /**
     * Toolbar (bulk) actions — rendered in a selection toolbar when the user
     * selects rows (slice 2.2). Each runs against the whole selected set
     * through the typed bulk endpoint (docs/CONTRACT.md, "Bulk actions"),
     * never one record at a time.
     *
     * @var array<int, BulkAction>
     */
    protected array $toolbarActions = [];

    protected bool $isSelectable = false;

    /**
     * Available groupings (slice 2.3). The active one is chosen by the
     * validated `group` query param, falling back to `defaultGroup`.
     *
     * @var array<string, Group>
     */
    protected array $groups = [];

    protected ?string $defaultGroup = null;

    protected int $recordsPerPage = 10;

    /** @var array<int, int> */
    protected array $recordsPerPageSelectOptions = [10, 25, 50];

    protected ?string $defaultSortColumn = null;

    protected string $defaultSortDirection = 'asc';

    protected ?EloquentBuilder $query = null;

    final public function __construct(protected ?string $name = null) {}

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    /**
     * The id clients use to address this table through the typed index
     * endpoint (docs/CONTRACT.md).
     */
    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * @param  array<int, Column>|Column  $columns
     */
    public function columns(array|Column $columns): static
    {
        $this->columns = array_merge($this->columns, is_array($columns) ? $columns : [$columns]);

        return $this;
    }

    /**
     * @param  array<int, SelectFilter|TextFilter|TrashedFilter>|SelectFilter|TextFilter|TrashedFilter  $filters
     */
    public function filters(array|SelectFilter|TextFilter|TrashedFilter $filters): static
    {
        $this->filters = array_merge($this->filters, is_array($filters) ? $filters : [$filters]);

        return $this;
    }

    /**
     * @param  array<int, Action>|Action  $actions
     */
    public function actions(array|Action $actions): static
    {
        $this->actions = array_merge($this->actions, is_array($actions) ? $actions : [$actions]);

        return $this;
    }

    /**
     * Actions rendered beside the table (not on rows) — typically a modal
     * create action opening the resource's form (docs/CONTRACT.md, "Modal
     * actions"). Mirrors Filament's header actions on list pages.
     *
     * @param  array<int, Action>|Action  $actions
     */
    public function headerActions(array|Action $actions): static
    {
        $this->headerActions = array_merge($this->headerActions, is_array($actions) ? $actions : [$actions]);

        return $this;
    }

    /**
     * Actions rendered on each row. Alias of `actions()`, mirroring Filament
     * v4's `recordActions()` naming (docs/CONTRACT.md, "Tables").
     *
     * @param  array<int, Action>|Action  $actions
     */
    public function recordActions(array|Action $actions): static
    {
        return $this->actions($actions);
    }

    /**
     * Toolbar (bulk) actions (slice 2.2) — shown in a selection toolbar that
     * appears once the user selects rows. Each runs against the whole selected
     * set through the typed bulk endpoint, never one record at a time.
     * Mirrors Filament v4's `toolbarActions()`.
     *
     * @param  array<int, BulkAction>|BulkAction  $actions
     */
    public function toolbarActions(array|BulkAction $actions): static
    {
        $this->toolbarActions = array_merge($this->toolbarActions, is_array($actions) ? $actions : [$actions]);

        return $this;
    }

    /**
     * Enable the record-selection checkbox column and the toolbar that acts on
     * the selected rows (slice 2.2; docs/CONTRACT.md, "Bulk actions"). Defaults
     * to on when called; call `selectable(false)` to disable.
     */
    public function selectable(bool $condition = true): static
    {
        $this->isSelectable = $condition;

        return $this;
    }

    /**
     * Register the grouping available for this table (slice 2.3). The active
     * one is chosen by the validated `group` query param, falling back to
     * `defaultGroup`. Each group splits the records into runs keyed by its
     * column value, with a header row per run (docs/CONTRACT.md, "Tables").
     *
     * @param  array<int, Group>|Group  $groups
     */
    public function groups(array|Group $groups): static
    {
        foreach (is_array($groups) ? $groups : [$groups] as $group) {
            $this->groups[$group->getColumn()] = $group;
        }

        return $this;
    }

    /**
     * The grouping applied when the client sends no `group` query param.
     * Pass a column name that names one of the groups registered via
     * `groups()`, or a Group instance. Mirrors Filament's defaultGroup().
     */
    public function defaultGroup(string|Group|null $group): static
    {
        $this->defaultGroup = $group instanceof Group ? $group->getColumn() : $group;

        return $this;
    }

    /**
     * @return array<string, Group>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Resolve the active grouping's column from a `?group=` request param,
     * falling back to the default group. An unknown column name (or no groups
     * registered) resolves to null — no grouping applied.
     */
    public function resolveActiveGroup(?string $requested = null): ?string
    {
        $column = $requested ?? $this->defaultGroup;

        return $column !== null && isset($this->groups[$column]) ? $column : null;
    }

    /**
     * @return array<int, Group>
     */
    public function getGroupsForDefinition(): array
    {
        return array_values($this->groups);
    }

    public function recordsPerPage(int $recordsPerPage): static
    {
        $this->recordsPerPage = max($recordsPerPage, 1);

        return $this;
    }

    /**
     * @param  array<int, int>  $options
     */
    public function recordsPerPageSelectOptions(array $options): static
    {
        $this->recordsPerPageSelectOptions = $options;

        return $this;
    }

    public function query(EloquentBuilder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * The sort applied when the client sends none. A client-provided `sort`
     * always overrides this (docs/CONTRACT.md, "Tables").
     */
    public function defaultSort(?string $column, string $direction = 'asc'): static
    {
        $this->defaultSortColumn = $column;
        $this->defaultSortDirection = $direction === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, SelectFilter|TextFilter|TrashedFilter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<int, Action>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @return array<int, Action>
     */
    public function getHeaderActions(): array
    {
        return $this->headerActions;
    }

    /**
     * @return array<int, BulkAction>
     */
    public function getToolbarActions(): array
    {
        return $this->toolbarActions;
    }

    public function isSelectable(): bool
    {
        return $this->isSelectable;
    }

    public function findAction(string $name): ?Action
    {
        foreach ($this->actions as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Resolve one toolbar (bulk) action by name, for the bulk endpoint
     * (slice 2.2; docs/CONTRACT.md, "Bulk actions").
     */
    public function findBulkAction(string $name): ?BulkAction
    {
        foreach ($this->toolbarActions as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * One record's cell values for the read-only view page (slice 1.6) —
     * id plus one key per column, serialized exactly as the table endpoint
     * serializes rows (docs/CONTRACT.md, "Tables").
     *
     * @return array<string, mixed>
     */
    public function getRecordValues(mixed $record): array
    {
        return $this->serializeRecord($record);
    }

    /**
     * Resolve a record by its primary key through this table's own query, so
     * the action endpoint never touches records the table doesn't see.
     */
    public function findRecord(string|int $key): mixed
    {
        return $this->getQuery()->find($key);
    }

    /**
     * Resolve a set of records by their primary keys through this table's own
     * query, for bulk actions (slice 2.2). Soft-delete aware: the
     * SoftDeletingScope is lifted so a bulk action (e.g. restore or force
     * delete) can resolve records that are currently trashed — the client only
     * sends the keys it selected, whatever their trash state. Returns the
     * records that exist; a caller comparing count against the requested keys
     * can detect records that are genuinely gone. (The scope removal mirrors
     * Filament's TrashedFilter::baseQuery, and keeps this resolvable on a
     * builder whose model larastan cannot prove is a soft-deleting one.)
     *
     * @param  array<int, string|int>  $keys
     * @return EloquentCollection<int, Model>
     */
    public function findRecords(array $keys): EloquentCollection
    {
        return $this->getQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->whereKey($keys)
            ->get();
    }

    /**
     * @return array<int, Column>
     */
    public function getSearchableColumns(): array
    {
        return array_values(array_filter($this->columns, static fn (Column $column): bool => $column->isSearchable()));
    }

    public function getRecordsPerPage(): int
    {
        return $this->recordsPerPage;
    }

    /**
     * @return array<int, int>
     */
    public function getRecordsPerPageSelectOptions(): array
    {
        return $this->recordsPerPageSelectOptions;
    }

    public function getDefaultSortColumn(): ?string
    {
        return $this->defaultSortColumn;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    /**
     * Serialize the table definition only (docs/CONTRACT.md, "Tables").
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->getId(),
            'columns' => array_map(static fn (Column $column): array => $column->toArray(), $this->columns),
            'recordsPerPage' => $this->getRecordsPerPage(),
            'recordsPerPageSelectOptions' => $this->getRecordsPerPageSelectOptions(),
        ];

        if ($this->isSelectable()) {
            $payload['selectable'] = true;
        }

        if ($this->heading !== null) {
            $payload['heading'] = $this->heading;
        }

        if ($this->filters !== []) {
            $payload['filters'] = array_map(static fn (SelectFilter|TextFilter|TrashedFilter $filter): array => $filter->toArray(), $this->filters);
        }

        if ($this->actions !== []) {
            $payload['actions'] = array_map(static fn (Action $action): array => $action->toArray(), $this->actions);
        }

        if ($this->headerActions !== []) {
            $payload['headerActions'] = array_map(static fn (Action $action): array => $action->toArray(), $this->headerActions);
        }

        if ($this->toolbarActions !== []) {
            $payload['toolbarActions'] = array_map(static fn (BulkAction $action): array => $action->toArray(), $this->toolbarActions);
        }

        if ($this->groups !== []) {
            // Available groupings (slice 2.3) — the client renders a selector
            // when more than one is offered. Each entry names the group column
            // and its display label.
            $payload['groups'] = array_map(
                static fn (Group $group): array => [
                    'column' => $group->getColumn(),
                    'label' => $group->getLabel(),
                    ...($group->isCollapsible() ? ['collapsible' => true] : []),
                ],
                $this->getGroupsForDefinition(),
            );
        }

        return $payload;
    }

    /**
     * Serialize the table definition plus one page of rows.
     *
     * @param  string|null  $sort  Column name; must be a sortable column.
     * @param  string  $direction  'asc' or 'desc'.
     * @param  string|null  $search  Global search term (matches searchable columns).
     * @param  array<string, string|array<int, string>>  $filters  Filter name => value(s).
     * @param  string|null  $group  Active grouping column (or null for none).
     * @return array<string, mixed>
     */
    public function toPayload(int $page = 1, ?int $perPage = null, ?string $sort = null, string $direction = 'asc', ?string $search = null, array $filters = [], ?string $group = null): array
    {
        $perPage = $perPage ?? $this->getRecordsPerPage();

        $query = $this->applySearch($this->getQuery(), $search);
        $query = $this->applyFilters($query, $filters);
        $query = $this->applySort($query, $sort, $direction, $group);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // A requested page beyond the last one clamps to the last page rather
        // than returning an empty, dangling page.
        if ($paginator->lastPage() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $paginator = $query->paginate($perPage, ['*'], 'page', $paginator->lastPage());
        }

        $activeGroup = $group !== null ? $this->groups[$group] : null;

        // Each row is annotated with its group key so the client can render
        // run headers; the distinct keys on this page also let the per-group
        // footer subtotals line up with the groups actually visible.
        $rows = $paginator->getCollection()
            ->map(fn (mixed $record): array => $this->serializeRecord($record, $activeGroup))
            ->all();

        $groupKeys = $activeGroup !== null
            ? array_values(array_unique(array_map(
                static fn (array $row): string => (string) $row['groupKey'],
                $rows,
            )))
            : [];

        return [
            ...$this->toArray(),
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'rows' => $rows,
            // Footer summaries (slice 1.7) — computed over the *filtered*
            // query, never a single page, so the totals reflect the whole
            // result set the user is looking at (docs/CONTRACT.md, "Tables").
            ...$this->serializeSummary($query),
            // Per-group footer subtotals (slice 2.3) — a summarizer row under
            // each group, computed over the filtered query scoped to that
            // group's key. Keyed by the same groupKey each row carries.
            ...$this->serializeGroupSummary($query, $activeGroup, $groupKeys),
        ];
    }

    /**
     * Compute every column's footer summaries against a query. Runs the
     * aggregate on the given (already filtered) builder — the values are
     * formatted server-side and keyed by column name; the `summary` key is
     * omitted entirely when no column declares a summarizer.
     *
     * The passed builder is never mutated: each summarizer receives its own
     * clone, so callers may pass their base query (getFullSummary) or the
     * filtered one (toPayload) without side effects.
     *
     * @return array{summary: array<string, array<int, array{label: string, value: mixed}>>}|array{}
     */
    public function serializeSummary(EloquentBuilder $query): array
    {
        $summary = [];

        foreach ($this->columns as $column) {
            $summarizers = $column->getSummarizers();

            if ($summarizers === []) {
                continue;
            }

            $summary[(string) $column->getName()] = array_map(
                static fn (Summarizer $summarizer): array => [
                    'label' => $summarizer->getLabel(),
                    'value' => $summarizer->getState(clone $query),
                ],
                $summarizers,
            );
        }

        return $summary === [] ? [] : ['summary' => $summary];
    }

    /**
     * Per-group footer subtotals (slice 2.3) — one summarizer row beneath
     * each visible group, like the whole-table footer summary but scoped to
     * that group's rows via `where(groupColumn, key)`.
     *
     * Only group keys present on the current page are computed, so a long,
     * paginated table doesn't aggregate every group in the dataset. Each
     * summarizer gets its own clone of the filtered query (never mutated),
     * consistent with serializeSummary(). The `groupSummary` key is omitted
     * entirely when no column declares a summarizer or when no group applies.
     *
     * @param  list<string>  $groupKeys  distinct group keys on the current page
     * @return array{groupSummary: array<string, array<string, array<int, array{label: string, value: mixed}>>>}|array{}
     */
    public function serializeGroupSummary(EloquentBuilder $query, ?Group $group, array $groupKeys): array
    {
        if ($group === null || $groupKeys === []) {
            return [];
        }

        $summaries = [];
        $groupColumn = $group->getColumn();

        foreach ($this->columns as $column) {
            $summarizers = $column->getSummarizers();

            if ($summarizers === []) {
                continue;
            }

            $columnName = (string) $column->getName();

            foreach ($groupKeys as $groupKey) {
                $scoped = (clone $query)->where($groupColumn, $groupKey);

                $summaries[$groupKey][$columnName] = array_map(
                    static fn (Summarizer $summarizer): array => [
                        'label' => $summarizer->getLabel(),
                        'value' => $summarizer->getState(clone $scoped),
                    ],
                    $summarizers,
                );
            }
        }

        return ['groupSummary' => $summaries];
    }

    /**
     * Footer summaries computed over the table's *unfiltered* query — the
     * record view page's totals (slice 1.7), which show the dataset-wide
     * aggregates regardless of how many filters the list may carry.
     *
     * @return array{summary: array<string, array<int, array{label: string, value: mixed}>>}|array{}
     */
    public function getFullSummary(): array
    {
        return $this->serializeSummary($this->getQuery());
    }

    protected function getQuery(): EloquentBuilder
    {
        if ($this->query === null) {
            throw new LogicException('Table must have a [query()] set before it can be paginated.');
        }

        return $this->query;
    }

    /**
     * Narrow the query to rows matching the global search term across every
     * searchable column (OR-ed LIKE clauses in one grouped WHERE).
     */
    protected function applySearch(EloquentBuilder $query, ?string $search): EloquentBuilder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $searchableColumns = $this->getSearchableColumns();

        if ($searchableColumns === []) {
            throw new LogicException('Cannot search a table with no searchable columns.');
        }

        return $query->where(function (EloquentBuilder $builder) use ($search, $searchableColumns): void {
            foreach ($searchableColumns as $column) {
                $builder->orWhere($column->getName(), 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * Narrow the query by each submitted filter whose value is non-empty.
     * Multiple filters match with WHERE IN.
     *
     * @param  array<string, string|array<int, string>>  $filters
     */
    protected function applyFilters(EloquentBuilder $query, array $filters): EloquentBuilder
    {
        foreach ($this->filters as $filter) {
            $name = (string) $filter->getName();

            if (! array_key_exists($name, $filters)) {
                continue;
            }

            $value = $filters[$name];
            $values = is_array($value) ? $value : [$value];
            $values = array_values(array_filter($values, static fn (string $v): bool => $v !== ''));

            if ($values === []) {
                continue;
            }

            if ($filter instanceof TextFilter) {
                // Free-text filters narrow to rows containing the term.
                $query->where($filter->getAttribute(), 'like', '%'.$values[0].'%');

                continue;
            }

            if ($filter instanceof TrashedFilter) {
                // Soft-delete view (slice 2.2). The ternary value maps to a
                // trash variant: 'with' shows every record (live + trashed),
                // 'only' shows the trashed ones, and '' (the default, when the
                // base query already excludes trashed) shows only live records.
                // The SoftDeletingScope is lifted first, then deleted_at is
                // filtered as a plain column — this mirrors Filament's
                // TrashedFilter::baseQuery and avoids relying on DynamDB macros
                // (withTrashed/onlyTrashed/...) that larastan cannot prove exist
                // on a builder whose model is a generic Eloquent model.
                $query->withoutGlobalScopes([SoftDeletingScope::class]);

                match ($values[0]) {
                    'with' => null,
                    'only' => $query->whereNotNull('deleted_at'),
                    default => $query->whereNull('deleted_at'),
                };

                continue;
            }

            if ($filter->isMultiple()) {
                $query->whereIn($filter->getAttribute(), $values);
            } else {
                $query->where($filter->getAttribute(), $values[0]);
            }
        }

        return $query;
    }

    /**
     * Order the query by the requested sort, falling back to the default sort.
     * A deterministic id tiebreaker keeps pagination stable across ties.
     */
    protected function applySort(EloquentBuilder $query, ?string $sort, string $direction, ?string $group = null): EloquentBuilder
    {
        $column = $sort ?? $this->getDefaultSortColumn();

        if ($column === null) {
            // No sort requested and no default sort — if a group is active it
            // still supplies the sole ordering so the page is contiguous.
            if ($group !== null) {
                $query = $query->reorder($group);
            }

            return $query;
        }

        // A client-provided sort carries its own direction; the default sort
        // supplies both its column and its direction when none is requested.
        $direction = $sort !== null ? $direction : $this->getDefaultSortDirection();

        // reorder() makes the requested ordering authoritative, replacing any
        // ordering baked into the table's query() rather than demoting it to
        // a tiebreaker (SQL applies ORDER BY left-to-right). Record grouping
        // (slice 2.3) becomes the *primary* key when active, with the sort as
        // a tiebreak within each group — mirroring Filament, which applies
        // the group order before the user sort
        // (HasRecords::getFilteredSortedTableQuery()). This keeps the page's
        // run headers contiguous instead of fragmented by a pre-existing sort.
        if ($group !== null) {
            $query = $query->reorder($group);
            $query = $column !== $group ? $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc') : $query;
        } else {
            $query = $query->reorder($column, $direction === 'desc' ? 'desc' : 'asc');
        }

        // Deterministic tiebreaker so pagination never shuffles between
        // requests when rows tie on the sort column.
        if ($column !== 'id') {
            $query = $query->orderByDesc('id');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRecord(mixed $record, ?Group $group = null): array
    {
        // The primary key wins as the row id (string | number in the contract;
        // PHP's json_encode re-encodes numeric strings as numbers anyway). A
        // column named `id` only contributes its label, never its value.
        $row = ['id' => $record->getKey()];

        // A row in a grouped table carries the value it's grouped under
        // (slice 2.3) so the client can render run headers between contiguously
        // ordered row groups (docs/CONTRACT.md, "Tables"). Emitted only when a
        // group is actually applied, and only for rows — the group *definition*
        // ships at the table level.
        if ($group !== null) {
            $row['groupKey'] = $group->getKeyFor($record);
            $row['groupTitle'] = $this->formatGroupTitle($group->getColumn(), $record);
        }

        foreach ($this->columns as $column) {
            if ($column->getName() === 'id') {
                continue;
            }

            $row[$column->getName()] = $column->serializeCell($record);
        }

        // Per-record visibility is evaluated now; only visible action names
        // ride on the row, the definitions live at the table level.
        $visibleActions = array_values(array_filter(
            $this->actions,
            static fn (Action $action): bool => $action->isVisibleFor($record),
        ));

        if ($visibleActions !== []) {
            $row['actions'] = array_map(static fn (Action $action): ?string => $action->getName(), $visibleActions);
        }

        return $row;
    }

    /**
     * A record's display label for a group header — the value of the grouping
     * column, passed through that column's formatter when one exists (so an
     * enum/badge/date column groups under its human value), else the raw
     * value.
     */
    protected function formatGroupTitle(string $column, mixed $record): string
    {
        foreach ($this->columns as $compiled) {
            if ($compiled->getName() === $column) {
                return (string) $compiled->getStateFor($record);
            }
        }

        if (isset($this->groups[$column])) {
            return (string) $this->groups[$column]->getKeyFor($record);
        }

        return (string) $record->getAttribute($column);
    }
}
