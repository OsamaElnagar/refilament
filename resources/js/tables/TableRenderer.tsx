import { router } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type {
    CellContext,
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Columns3,
    ListFilter,
    MoreHorizontal,
    Search,
    X,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import { renderNotification } from '@/notifications/renderNotification';
import type { NotificationPayload } from '@/notifications/renderNotification';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow as TableRowPrimitive,
} from '@/components/ui/table';
import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import { cn } from '@/lib/utils';
import ActionModal from '@/tables/action-modal';
import { Cell } from '@/tables/cell';
import { ICONS } from '@/components/icon';
import { FilterPanel, type FilterAccessors } from '@/tables/filter-panel';
import HeaderActions from '@/tables/header-actions';
import type {
    TableAction,
    TableActionColor,
    TableBulkAction,
    TableColumn,
    TableFilter,
    TableGroup,
    TablePayload,
    TableRow,
    TableSummaryMap,
} from '@/tables/types';

const STORAGE_PREFIX = 'refilament:table:columns:';

/**
 * Endpoint overrides that let a TableRenderer serve a relation-manager table
 * hosted under a record page (slice 1.8). When `source` is set, every fetch
 * points at the relation's scoped endpoints instead of this resource table's,
 * and — because the table is embedded in a page rather than owning its own URL
 * — the view state is never mirrored into `window.location`.
 */
export interface TableSource {
    /** The base index URL; the query-param string is appended. */
    index: string;
    /** Submit endpoint for a row (modal edit / inline) and a header action. */
    action: (recordId: string | number | undefined, actionName: string) => string;
    /** Record pre-fill base URL for the edit modal (schema appended by it). */
    record: (recordId: string | number) => string;
}

interface TableRendererProps {
    initial: TablePayload;
    /** Endpoint overrides for a relation-manager table hosted on a record page. */
    source?: TableSource;
}

interface ConfirmState {
    row: TableRow;
    action: TableAction;
}

interface BulkConfirmState {
    action: TableBulkAction;
}

/** One indicator shown while the search term or a column filter is active. */
interface ActiveFilter {
    kind: 'search' | 'filter';
    /** Filter name for `filter` indicators; the search indicator is synthetic. */
    id: string;
    label: string;
    valueLabel: string;
}

/** Tailwind text-color classes per action color, layered over Button's ghost variant. */
const ACTION_COLORS: Record<TableActionColor, string> = {
    primary: 'text-indigo-600 hover:bg-indigo-50',
    secondary: 'text-zinc-600 hover:bg-zinc-100',
    danger: 'text-rose-600 hover:bg-rose-50',
    success: 'text-emerald-600 hover:bg-emerald-50',
    warning: 'text-amber-600 hover:bg-amber-50',
    info: 'text-sky-600 hover:bg-sky-50',
};

/** Tailwind icon color classes per action color, over the ghost button. */
const ACTION_ICON_COLORS: Record<TableActionColor, string> = {
    primary: 'text-indigo-600 dark:text-indigo-400',
    secondary: 'text-zinc-500 dark:text-zinc-400',
    danger: 'text-rose-600 dark:text-rose-400',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-600 dark:text-sky-400',
};

interface RowActionsProps {
    row: TableRow;
    /**
     * The visible actions this row carries: flat action names and group
     * entries `{ name, items }` (the server lists each group's visible
     * members explicitly — slice 2.5).
     */
    entries: Array<string | { name: string; items: string[] }>;
    actions: Map<string, TableAction>;
    groups: TableAction[];
    /** Composite key of the action currently running on this row. */
    runningKey: string | null;
    onInvoke: (row: TableRow, action: TableAction) => void;
}

/**
 * The row's actions column (professional actions slice — docs/ROADMAP.md
 * "2.5 Table & bulk actions"). Flat actions render as icon buttons with a
 * tooltip (Filament's row-action pattern); a group renders as an ellipsis
 * overflow menu holding exactly the member actions the server listed as
 * visible for this record. The icon, color and tooltip are the serialized
 * server-side definitions.
 */
function RowActions({ row, entries, actions, groups, runningKey, onInvoke }: RowActionsProps) {
    const flat = entries
        .filter((entry): entry is string => typeof entry === 'string')
        .map((name) => actions.get(name))
        .filter((action): action is TableAction => action !== undefined);

    const groupEntries = entries
        .filter((entry): entry is { name: string; items: string[] } => typeof entry !== 'string')
        .map((entry) => {
            const group = groups.find((candidate) => candidate.name === entry.name);

            return group === undefined ? undefined : { group, visibleNames: new Set(entry.items) };
        })
        .filter((entry): entry is { group: TableAction; visibleNames: Set<string> } => entry !== undefined);

    const renderTrigger = (action: TableAction) => {
        const isRunning = runningKey === `${row.id}:${action.name}`;
        const Icon = action.icon ? ICONS[action.icon] : undefined;

        return (
            <Tooltip key={action.name}>
                <TooltipTrigger
                    render={
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            disabled={isRunning}
                            onClick={() => onInvoke(row, action)}
                            aria-label={action.tooltip ?? action.label}
                        >
                            {isRunning ? (
                                <span className="size-3.5 animate-pulse rounded-full border-2 border-current border-t-transparent" />
                            ) : Icon ? (
                                <Icon
                                    className={cn('size-3.5', ACTION_ICON_COLORS[action.color ?? 'secondary'])}
                                    aria-hidden="true"
                                />
                            ) : (
                                <span className="text-xs">{action.label}</span>
                            )}
                        </Button>
                    }
                />
                <TooltipContent>{action.tooltip ?? action.label}</TooltipContent>
            </Tooltip>
        );
    };

    return (
        <div className="flex items-center justify-end gap-0.5">
            {flat.map((action) => renderTrigger(action))}
            {groupEntries.map(({ group, visibleNames }) => {
                const visibleItems = (group.items ?? []).filter((item) => visibleNames.has(item.name));

                if (visibleItems.length === 0) {
                    return null;
                }

                const GroupIcon = group.icon ? ICONS[group.icon] : MoreHorizontal;

                return (
                    <DropdownMenu key={group.name}>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-6 text-muted-foreground"
                                    aria-label={group.label}
                                >
                                    <GroupIcon className="size-3.5" aria-hidden="true" />
                                </Button>
                            }
                        />
                        <DropdownMenuContent align="end" className="min-w-36">
                            {visibleItems.map((item) => {
                                const isRunning = runningKey === `${row.id}:${item.name}`;
                                const ItemIcon = item.icon ? ICONS[item.icon] : undefined;

                                return (
                                    <DropdownMenuItem
                                        key={item.name}
                                        disabled={isRunning}
                                        onSelect={() => onInvoke(row, item)}
                                        className={cn(
                                            'gap-2',
                                            item.color === 'danger' && 'text-rose-600 focus:text-rose-600 dark:text-rose-400 dark:focus:text-rose-400',
                                        )}
                                    >
                                        {isRunning ? (
                                            <span className="size-4 animate-pulse rounded-full border-2 border-current border-t-transparent" />
                                        ) : ItemIcon ? (
                                            <ItemIcon
                                                className={cn('size-4', ACTION_ICON_COLORS[item.color ?? 'secondary'])}
                                                aria-hidden="true"
                                            />
                                        ) : null}
                                        {item.label}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            })}
        </div>
    );
}

/**
 * JSON POST headers carrying the session's CSRF token — the panel routes run
 * inside the framework's `web` middleware group, so every POST must validate.
 * The token comes from the csrf-token meta (the raw session token the CSRF
 * middleware accepts via the X-CSRF-TOKEN header).
 */
function postHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };

    const csrfToken = readCsrfToken();

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return headers;
}

/** Stable string signature of the current filter state, for the fetch guard. */
function filtersSignature(filters: ColumnFiltersState): string {
    return filters
        .map((filter) => `${filter.id}:${Array.isArray(filter.value) ? (filter.value as string[]).sort().join('|') : String(filter.value ?? '')}`)
        .sort()
        .join('&');
}

/**
 * Build the query params sent to the index endpoint — page, perPage, one
 * sort, a global search term and one entry per active filter. The URL
 * mirrors these exact param names (see readUrlState), so the same builder
 * serves both the fetch and the URL write-back.
 */
function buildTableParams(
    page: number,
    perPage: number,
    sorting: SortingState,
    search: string,
    filters: ColumnFiltersState,
    group?: string,
): URLSearchParams {
    const params = new URLSearchParams({ page: String(page), perPage: String(perPage) });
    const sort = sorting[0];

    if (sort) {
        params.set('sort', sort.id);
        params.set('direction', sort.desc ? 'desc' : 'asc');
    }

    const term = search.trim();

    if (term) {
        params.set('search', term);
    }

    if (group) {
        params.set('group', group);
    }

    for (const filter of filters) {
        if (filter.value === null || filter.value === undefined || filter.value === '') {
            continue;
        }

        if (Array.isArray(filter.value)) {
            for (const value of filter.value as string[]) {
                params.append(`filter[${filter.id}][]`, value);
            }
        } else {
            params.append(`filter[${filter.id}]`, String(filter.value));
        }
    }

    return params;
}

/** Stable signature of the settled text-filter values, for the debounce guard. */
function textFilterSignature(filters: ColumnFiltersState, names: ReadonlySet<string>): string {
    return filters
        .filter((filter) => names.has(filter.id))
        .map((filter) => `${filter.id}:${String(filter.value ?? '')}`)
        .sort()
        .join('&');
}

/**
 * Canonical, order-independent representation of a query string, with
 * PHP-style filter indexes normalized to bracket form. The server rebuilds
 * `filter[status][]` as `filter[status][0]` on every initial Inertia render
 * (http_build_query), and shared links may arrive in either order — so this
 * is what the write-back compares against to decide whether the URL already
 * reflects the view state.
 */
function canonicalParams(search: string): string {
    const entries: string[] = [];

    for (const [key, value] of new URLSearchParams(search).entries()) {
        // Collapse every filter bracket form — plain (`filter[name]`), empty
        // (`filter[name][]`) and PHP-indexed (`filter[name][0]`) — onto one
        // key, exactly as readUrlState groups them, so the comparison only
        // sees view differences, never spelling differences.
        const normalizedKey = key.replace(/^filter\[([^\]]+)\](?:\[[^\]]*\])?$/, 'filter[$1]');
        entries.push(`${normalizedKey}=${value.trim()}`);
    }

    return entries.sort().join('&');
}

interface UrlTableState {
    page: number;
    perPage: number;
    sorting: SortingState;
    search: string;
    textInputs: Record<string, string>;
    columnFilters: ColumnFiltersState;
    /** Active grouping column (slice 2.3); '' when the default/no group applies. */
    group: string;
}

/**
 * Derive the initial view state from the URL query string. Params mirror
 * the index endpoint: page, perPage, sort + direction, search and
 * filter[<name>] (repeated `filter[<name>][]` for multiple filters).
 * Values are validated against the table definition so a stale or
 * hand-edited link degrades to defaults instead of erroring.
 */
function readUrlState(href: string, payload: TablePayload): UrlTableState {
    const params = new URL(href).searchParams;
    const sortable = new Set(payload.columns.filter((column) => column.sortable).map((column) => column.name));

    const pageParam = Number(params.get('page'));
    const page = Number.isInteger(pageParam) && pageParam >= 1 ? pageParam : 1;

    const perPageParam = Number(params.get('perPage'));
    const perPage = payload.recordsPerPageSelectOptions.includes(perPageParam)
        ? perPageParam
        : payload.recordsPerPage;

    // The active grouping column (slice 2.3). Only a registered group is
    // honored — a stale link's `group` degrades to the default group.
    const availableGroups = new Set((payload.groups ?? []).map((group) => group.column));
    const group = params.get('group') ?? '';
    const activeGroup = availableGroups.has(group) ? group : '';

    const sorting: SortingState = [];
    const sort = params.get('sort') ?? '';

    if (sort !== '' && sortable.has(sort)) {
        sorting.push({ id: sort, desc: params.get('direction') === 'desc' });
    }

    // Values are trimmed so the hydrated state always matches what the
    // debounce effects would settle on — a trailing-space term in a shared
    // URL can't look like a change on mount and clobber the URL-provided
    // page.
    const search = (params.get('search') ?? '').trim();
    const columnFilters: ColumnFiltersState = [];
    const textInputs: Record<string, string> = {};

    // Collect every filter value regardless of bracket form: the client
    // writes `filter[<name>]` / `filter[<name>][]`, but the server rebuilds
    // the query string as `filter[<name>][0]` (PHP http_build_query) on its
    // initial Inertia render, so shared links arrive indexed. Only filters
    // the table actually defines are read — unknown names in the URL (stale
    // links) are silently dropped, never sent to the server.
    const filterValues = new Map<string, string[]>();

    for (const [key, value] of params.entries()) {
        const match = key.match(/^filter\[([^\]]+)\](?:\[[^\]]*\])?$/);

        if (match === null || value.trim() === '') {
            continue;
        }

        const name = match[1];
        const values = filterValues.get(name) ?? [];
        values.push(value.trim());
        filterValues.set(name, values);
    }

    for (const definition of payload.filters ?? []) {
        const values = filterValues.get(definition.name);

        if (values === undefined) {
            continue;
        }

        if (definition.type === 'text') {
            const value = values[0];
            textInputs[definition.name] = value;
            columnFilters.push({ id: definition.name, value });
        } else if (definition.type === 'select' && definition.multiple) {
            columnFilters.push({ id: definition.name, value: values });
        } else {
            columnFilters.push({ id: definition.name, value: values[0] });
        }
    }

    return { page, perPage, sorting, search, textInputs, columnFilters, group: activeGroup };
}

/**
 * Build the initial visibility map from the table definition, overlaid with
 * the per-table preference persisted in localStorage. Non-toggleable columns
 * never appear in the map — TanStack treats absent keys as visible, so they
 * can't be hidden. Stored preferences for unknown or non-toggleable columns
 * are ignored, so a stale payload can never hide a column the server no
 * longer offers.
 */
function readStoredVisibility(tableId: string | undefined, columns: TableColumn[]): VisibilityState {
    const state: VisibilityState = {};

    for (const column of columns) {
        if (column.toggleable) {
            state[column.name] = true;
        }
    }

    if (!tableId) {
        return state;
    }

    try {
        const stored = JSON.parse(localStorage.getItem(`${STORAGE_PREFIX}${tableId}`) ?? '{}') as Record<
            string,
            unknown
        >;

        for (const column of columns) {
            if (column.toggleable && typeof stored[column.name] === 'boolean') {
                state[column.name] = stored[column.name] as boolean;
            }
        }
    } catch {
        // Unreadable storage — fall back to every column visible.
    }

    return state;
}

/**
 * The initial view state for an embedded table (one with a `source` override):
 * no URL to hydrate from, so defaults — first page, the table's per-page, no
 * sort/search/filter, and the table's default group.
 */
function defaultUrlState(payload: TablePayload): UrlTableState {
    return {
        page: 1,
        perPage: payload.recordsPerPage,
        sorting: [],
        search: '',
        textInputs: {},
        columnFilters: [],
        group: payload.activeGroup ?? '',
    };
}

/**
 * Navigate an action whose row carries a resolved per-record URL (record
 * navigation slice) — the counterpart of the action endpoint for navigation
 * actions (ViewAction, closure-URL actions). Returns true when the action
 * was a navigation (handled); false when the caller should run it as a
 * normal endpoint action instead.
 */
function navigateActionUrl(row: TableRow, action: TableAction): boolean {
    const target = row.actionUrls?.[action.name];

    if (!target?.url) {
        return false;
    }

    if (target.openUrlInNewTab) {
        window.open(target.url, '_blank', 'noopener,noreferrer');
    } else {
        router.visit(target.url);
    }

    return true;
}

/**
 * Inline-editable boolean cell (slice: editable columns). Renders the shared
 * Checkbox (kind 'checkbox') or Switch (kind 'toggle') and calls `onCommit`
 * with the toggled value; `onColor` tints the checked state.
 */
const editableColorClasses: Record<string, string> = {
    primary: 'data-[checked]:bg-primary',
    secondary: 'data-[checked]:bg-secondary',
    success: 'data-[checked]:bg-success',
    danger: 'data-[checked]:bg-danger',
    warning: 'data-[checked]:bg-warning',
    info: 'data-[checked]:bg-info',
};

function EditableBooleanCell({
    value,
    column,
    pending,
    onCommit,
}: {
    value: boolean;
    column: TableColumn;
    pending: boolean;
    onCommit: (next: boolean) => void;
}) {
    const checked = value === true;
    const tint = column.onColor ? editableColorClasses[column.onColor] : undefined;

    if (column.kind === 'toggle') {
        return (
            <Switch
                checked={checked}
                disabled={pending}
                onCheckedChange={(next) => onCommit(next === true)}
                className={tint}
            />
        );
    }

    return (
        <Checkbox
            checked={checked}
            disabled={pending}
            onCheckedChange={(next) => onCommit(next === true)}
            className={tint}
        />
    );
}

/**
 * Inline-editable select cell (slice: editable columns). Renders a compact
 * native `<select>` over the column's options and calls `onCommit` with the
 * chosen option value; `placeholder` shows when nothing is selected.
 */
function EditableSelectCell({
    value,
    column,
    pending,
    onCommit,
}: {
    value: string | number | null;
    column: TableColumn;
    pending: boolean;
    onCommit: (next: string) => void;
}) {
    const options = column.options ?? [];

    return (
        <select
            value={value === null || value === undefined ? '' : String(value)}
            disabled={pending}
            onChange={(event) => onCommit(event.target.value)}
            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-7 w-fit max-w-[12rem] rounded-md border bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
        >
            {column.placeholder ? <option value="">{column.placeholder}</option> : null}
            {options.map((option) => (
                <option key={option.value} value={option.value} disabled={option.isDisabled}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

/**
 * Inline-editable text cell (slice: editable columns). Renders a compact
 * `<input>` that commits on Enter or blur; Escape reverts to the server value.
 */
function EditableTextCell({
    value,
    column,
    pending,
    onCommit,
}: {
    value: string | number | null;
    column: TableColumn;
    pending: boolean;
    onCommit: (next: string) => void;
}) {
    const [draft, setDraft] = useState<string>(value === null || value === undefined ? '' : String(value));

    useEffect(() => {
        setDraft(value === null || value === undefined ? '' : String(value));
    }, [value]);

    const commit = () => {
        if (draft !== String(value)) {
            onCommit(draft);
        }
    };

    return (
        <input
            type={column.type ?? 'text'}
            inputMode={column.inputMode}
            step={column.step}
            maxLength={column.maxLength}
            placeholder={column.placeholder}
            value={draft}
            disabled={pending}
            onChange={(event) => setDraft(event.target.value)}
            onBlur={commit}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    commit();
                    (event.target as HTMLInputElement).blur();
                } else if (event.key === 'Escape') {
                    setDraft(String(value));
                    (event.target as HTMLInputElement).blur();
                }
            }}
            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-7 w-full min-w-[6rem] max-w-[14rem] rounded-md border bg-transparent px-2 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
        />
    );
}

export default function TableRenderer({ initial, source }: TableRendererProps) {
    // The view state mirrors the URL query string (same param names as the
    // index endpoint) so the back button and shareable links restore the
    // exact view. Values are validated against the definition — a stale link
    // degrades to defaults instead of erroring. An embedded relation table has
    // no URL of its own (the owner's edit page owns the address bar), so it
    // starts from defaults and never writes back.
    const initialUrlState = useMemo(() => {
        if (source) {
            return defaultUrlState(initial);
        }

        return readUrlState(window.location.href, initial);
    }, [initial, source]);

    const [rows, setRows] = useState<TableRow[]>(initial.rows);
    // The live table definition. A page-owned table hydrates it from the
    // initial payload; an embedded relation table passes an empty shell
    // (zero columns/actions), so the first fetch populates the definition
    // too — otherwise rows would render with no columns to display.
    const [definition, setDefinition] = useState<TablePayload>(initial);
    const [summary, setSummary] = useState<TableSummaryMap | undefined>(initial.summary);
    const [groupSummary, setGroupSummary] = useState<Record<string, TableSummaryMap> | undefined>(initial.groupSummary);
    const [page, setPage] = useState(initialUrlState.page);
    const [perPage, setPerPage] = useState(initialUrlState.perPage);
    const [total, setTotal] = useState(initial.total);
    const [lastPage, setLastPage] = useState(initial.lastPage);
    const [sorting, setSorting] = useState<SortingState>(initialUrlState.sorting);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>(initialUrlState.columnFilters);
    // Live input values of the debounced text filters, keyed by filter name.
    // They sync into columnFilters (and only then trigger a fetch) after the
    // user pauses typing — never on every keystroke.
    const [textInputs, setTextInputs] = useState<Record<string, string>>(initialUrlState.textInputs);
    const [searchInput, setSearchInput] = useState(initialUrlState.search);
    const [searchTerm, setSearchTerm] = useState(initialUrlState.search);
    const [isLoading, setIsLoading] = useState(false);
    // A server-hydrated page matches its own lastLoaded marker on mount and so
    // skips a redundant first fetch; an embedded relation table has no rows to
    // hydrate, so it starts already "dirty" (refreshKey 1) and fetches on mount.
    const [refreshKey, setRefreshKey] = useState(() => (source ? 1 : 0));
    // Slice: editable columns. Keys are `${rowId}:${columnName}` for the cells
    // currently awaiting their update round-trip (disables the control).
    const [editingPending, setEditingPending] = useState<Set<string>>(() => new Set());
    const [confirm, setConfirm] = useState<ConfirmState | null>(null);
    const [edit, setEdit] = useState<ConfirmState | null>(null);
    const [runningAction, setRunningAction] = useState<string | null>(null);

    // Record selection is purely client-owned (slice 2.2): the user toggles
    // checkboxes and the selected keys live in this Set. The bulk endpoint
    // receives the concrete selected keys — the server never remembers a
    // selection between requests (docs/ARCHITECTURE.md — no fake Livewire).
    const [selectedRecords, setSelectedRecords] = useState<Set<string | number>>(() => new Set());
    const [bulkConfirm, setBulkConfirm] = useState<BulkConfirmState | null>(null);
    const [runningBulkAction, setRunningBulkAction] = useState<string | null>(null);

    // Active record grouping (slice 2.3). The server resolves the initial one
    // (URL `group` param, else the table's default group); the selector
    // switches it, which re-fetches and writes the `group` param back to the
    // URL. Grouped columns yield a header row per run (client-side collapse is
    // pure display state, never persisted).
    const [group, setGroup] = useState<string>(initialUrlState.group || initial.activeGroup || '');
    const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(() => new Set());

    // Column visibility is a client-side concern: the server always serves
    // every column, the React runtime hides toggled-off ones (and remembers
    // the choice per table in localStorage).
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(() =>
        readStoredVisibility(initial.id, initial.columns),
    );
    const [isColumnsOpen, setIsColumnsOpen] = useState(false);

    // Filter-layout state (mirrors Filament's FiltersLayout). 'dropdown' hosts
    // the panel in a Base UI Popover (its dismissable layer nests the filter
    // controls' own popups — a Select menu opened inside stays open on option
    // clicks instead of dismissing the panel); 'modal' a dialog. Collapsible
    // above/side layouts toggle the panel through the toolbar trigger.
    const [isFiltersOpen, setIsFiltersOpen] = useState(false);
    const [isFilterModalOpen, setFilterModalOpen] = useState(false);
    const [filtersExpanded, setFiltersExpanded] = useState(false);

    const lastLoaded = useRef({
        page: initial.page,
        perPage: initial.perPage,
        sort: '',
        direction: '',
        search: '',
        filters: '',
        group: '',
        refreshKey: 0,
    });
    const aborters = useRef<Record<string, AbortController>>({});

    // Flat action definitions (excludes groups — those are matched by name in
    // the row's action list and rendered as overflow menus).
    const actionsById = useMemo(() => {
        const map = new Map<string, TableAction>();

        for (const action of definition.actions ?? []) {
            if (!action.group) {
                map.set(action.name, action);
            }
        }

        return map;
    }, [definition.actions]);

    // Group definitions, for the overflow menus.
    const groups = useMemo(() => (definition.actions ?? []).filter((action) => action.group), [definition.actions]);

    const runAction = async (row: TableRow, action: TableAction): Promise<void> => {
        const tableId = definition.id;

        if (!tableId) {
            return;
        }

        setConfirm(null);
        setRunningAction(`${row.id}:${action.name}`);

        try {
            const actionUrl = source
                ? source.action(row.id, action.name)
                : panelUrl(`/table/${tableId}/action/${action.name}`);

            const response = await fetch(actionUrl, {
                method: 'POST',
                headers: postHeaders(),
                body: JSON.stringify({ record: String(row.id) }),
            });

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
                notification?: NotificationPayload;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                throw new Error(payload.errors?.action?.[0] ?? `Action returned ${response.status}`);
            }

            if (payload.notification) {
                renderNotification(payload.notification);
            } else {
                toast.success(payload.message ?? `${action.label} succeeded.`);
            }
            setRefreshKey((key) => key + 1);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Action failed.');
        } finally {
            setRunningAction(null);
        }
    };

    const runBulkAction = async (action: TableBulkAction): Promise<void> => {
        const tableId = definition.id;

        if (!tableId || selectedRecords.size === 0) {
            return;
        }

        setBulkConfirm(null);
        setRunningBulkAction(action.name);

        try {
            const response = await fetch(panelUrl(`/table/${tableId}/bulk/${action.name}`), {
                method: 'POST',
                headers: postHeaders(),
                body: JSON.stringify({ records: [...selectedRecords].map(String) }),
            });

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
                notification?: NotificationPayload;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                throw new Error(payload.errors?.action?.[0] ?? `Action returned ${response.status}`);
            }

            if (payload.notification) {
                renderNotification(payload.notification);
            } else {
                toast.success(payload.message ?? `${action.label} succeeded.`);
            }
            setSelectedRecords(new Set());
            setRefreshKey((key) => key + 1);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Action failed.');
        } finally {
            setRunningBulkAction(null);
        }
    };

    // Slice: editable columns — write one column of one record through the
    // typed record-column endpoint. The cell updates optimistically; on
    // success the server's fresh cell value reconciles the row, on failure a
    // refetch restores the true state.
    const commitEditable = async (
        rowId: string | number,
        column: TableColumn,
        nextValue: unknown,
    ): Promise<void> => {
        const tableId = definition.id;

        if (!tableId) {
            return;
        }

        const key = `${rowId}:${column.name}`;

        setRows((prev) =>
            prev.map((row) => (row.id === rowId ? { ...row, [column.name]: { value: nextValue } } : row)),
        );
        setEditingPending((prev) => {
            const next = new Set(prev);
            next.add(key);
            return next;
        });

        try {
            const response = await fetch(panelUrl(`/table/${tableId}/record/${rowId}/column/${column.name}`), {
                method: 'POST',
                headers: postHeaders(),
                body: JSON.stringify({ value: nextValue }),
            });

            const payload = (await response.json()) as {
                data?: { value?: boolean };
                error?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                throw new Error(
                    payload.errors?.[column.name]?.[0] ?? payload.error ?? `Update returned ${response.status}`,
                );
            }

            if (payload.data) {
                setRows((prev) =>
                    prev.map((row) => (row.id === rowId ? { ...row, [column.name]: payload.data } : row)),
                );
            }
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Update failed.');
            setRefreshKey((key) => key + 1);
        } finally {
            setEditingPending((prev) => {
                const next = new Set(prev);
                next.delete(key);
                return next;
            });
        }
    };

    const columns = useMemo<ColumnDef<TableRow>[]>(
        () => [
            ...(definition.selectable
                ? [
                      {
                          id: 'select',
                          accessorKey: 'select',
                          header: () => {
                              const pageIds = rows.map((row) => row.id);
                              const allSelected =
                                  pageIds.length > 0 && pageIds.every((id) => selectedRecords.has(id));
                              const someSelected =
                                  pageIds.some((id) => selectedRecords.has(String(id))) && !allSelected;

                              return (
                                  <Checkbox
                                      aria-label="Select all rows on this page"
                                      checked={allSelected}
                                      indeterminate={someSelected}
                                      onCheckedChange={(checked) => {
                                          setSelectedRecords((current) => {
                                              const next = new Set(current);

                                              // A click on a filled/indeterminate box deselects the
                                              // page; on an empty box it selects the page.
                                              if (checked) {
                                                  for (const id of pageIds) {
                                                      next.add(String(id));
                                                  }
                                              } else {
                                                  for (const id of pageIds) {
                                                      next.delete(String(id));
                                                  }
                                              }

                                              return next;
                                          });
                                      }}
                                  />
                              );
                          },
                          enableSorting: false,
                          cell: ({ row }: CellContext<TableRow, unknown>) => {
                              const id = String(row.original.id);

                              return (
                                  <Checkbox
                                      aria-label={`Select record ${id}`}
                                      checked={selectedRecords.has(id)}
                                      onCheckedChange={(checked) => {
                                          setSelectedRecords((current) => {
                                              const next = new Set(current);

                                              if (checked) {
                                                  next.add(id);
                                              } else {
                                                  next.delete(id);
                                              }

                                              return next;
                                          });
                                      }}
                                  />
                              );
                          },
                      },
                  ]
                : []),
            ...definition.columns.map((column) => ({
                id: column.name,
                // The server serializes relationship columns under the literal
                // dot key (e.g. "category.name"), so resolve the value by that
                // exact key rather than TanStack's nested dot-lookup.
                accessorFn: (row: TableRow) => row[column.name],
                header: column.label,
                enableSorting: column.sortable ?? false,
                size: column.width ? parseInt(column.width, 10) : undefined,
                cell: (info: CellContext<TableRow, unknown>) => {
                    if (column.editable && column.kind === 'text') {
                        const row = info.row.original;

                        return (
                            <EditableTextCell
                                value={info.getValue() as string | number | null}
                                column={column}
                                pending={editingPending.has(`${row.id}:${column.name}`)}
                                onCommit={(next) => {
                                    void commitEditable(row.id, column, next);
                                }}
                            />
                        );
                    }

                    if (column.editable && column.kind === 'select') {
                        const row = info.row.original;

                        return (
                            <EditableSelectCell
                                value={info.getValue() as string | number | null}
                                column={column}
                                pending={editingPending.has(`${row.id}:${column.name}`)}
                                onCommit={(next) => {
                                    void commitEditable(row.id, column, next);
                                }}
                            />
                        );
                    }

                    if (column.editable && (column.kind === 'checkbox' || column.kind === 'toggle')) {
                        const row = info.row.original;
                        const raw = info.getValue() as { value?: boolean } | null;

                        return (
                            <EditableBooleanCell
                                value={raw?.value === true}
                                column={column}
                                pending={editingPending.has(`${row.id}:${column.name}`)}
                                onCommit={(next) => {
                                    void commitEditable(row.id, column, next);
                                }}
                            />
                        );
                    }

                    return (
                        <Cell
                            value={info.getValue()}
                            placeholder={column.placeholder}
                            presentation={column}
                        />
                    );
                },
            })),
            ...((definition.actions?.length ?? 0) > 0
                ? [
                      {
                          id: 'actions',
                          header: '',
                          enableSorting: false,
                          cell: (info: CellContext<TableRow, unknown>) => {
                              const row = info.row.original;
                              const entries = row.actions ?? [];

                              if (entries.length === 0) {
                                  return null;
                              }

                              return (
                                  <RowActions
                                      row={row}
                                      entries={entries}
                                      actions={actionsById}
                                      groups={groups}
                                      runningKey={runningAction}
                                      onInvoke={(targetRow, action) => {
                                          // A navigation action (record
                                          // navigation slice) goes to its
                                          // per-record URL — never through the
                                          // action endpoint.
                                          if (navigateActionUrl(targetRow, action)) {
                                              return;
                                          }

                                          if (action.type === 'edit') {
                                              // Modal edit (slice 1.2): the shared ActionModal
                                              // fetches the record's values + the form document,
                                              // and submits through this action's endpoint.
                                              setEdit({ row: targetRow, action });
                                          } else if (action.requiresConfirmation) {
                                              setConfirm({ row: targetRow, action });
                                          } else {
                                              runAction(targetRow, action);
                                          }
                                      }}
                                  />
                              );
                          },
                      },
                  ]
                : []),
        ],
        [definition.columns, definition.actions, definition.selectable, actionsById, groups, runningAction, rows, selectedRecords, editingPending, commitEditable],
    );

    const activeSort = sorting[0];

    const hasSearchableColumns = definition.columns.some((column) => column.searchable ?? false);
    const hasFilters = (definition.filters?.length ?? 0) > 0;
    const hasToggleableColumns = definition.columns.some((column) => column.toggleable ?? false);
    const hasHeaderActions = (definition.headerActions?.length ?? 0) > 0;
    const hasGroups = (definition.groups ?? []).length > 0;

    // Where the filters render (mirrors Filament's FiltersLayout enum).
    const filtersLayout = definition.filtersLayout ?? 'dropdown';
    const isDialogLayout = filtersLayout === 'dropdown' || filtersLayout === 'modal';
    const isAboveLayout = filtersLayout === 'above-content' || filtersLayout === 'above-content-collapsible';
    const isBelowLayout = filtersLayout === 'below-content';
    const isBeforeLayout = filtersLayout === 'before-content' || filtersLayout === 'before-content-collapsible';
    const isAfterLayout = filtersLayout === 'after-content' || filtersLayout === 'after-content-collapsible';
    const isCollapsible =
        filtersLayout === 'above-content-collapsible' ||
        filtersLayout === 'before-content-collapsible' ||
        filtersLayout === 'after-content-collapsible';
    const isHiddenLayout = filtersLayout === 'hidden';
    // The toolbar trigger: dialog layouts always show it, collapsible above/side
    // layouts use it to expand/collapse the panel; inline (above/below) and
    // always-visible side layouts render no trigger.
    const showFilterTrigger =
        hasFilters &&
        !isHiddenLayout &&
        (isDialogLayout || filtersLayout === 'above-content-collapsible' || isBeforeLayout || isAfterLayout);

    const groupDefinition = useMemo<TableGroup | undefined>(
        () => definition.groups?.find((candidate) => candidate.column === group),
        [definition.groups, group],
    );

    const textFilterNames = useMemo(() => {
        const names = new Set<string>();

        for (const filter of definition.filters ?? []) {
            if (filter.type === 'text') {
                names.add(filter.name);
            }
        }

        return names;
    }, [definition.filters]);

    // The last *settled* search term and text-filter values. The debounce
    // effects only reset the page when these actually change, so hydrating
    // state from a shared URL on mount can't clobber the URL-provided page.
    const committedSearch = useRef(initialUrlState.search);
    const committedTextFilters = useRef(textFilterSignature(initialUrlState.columnFilters, textFilterNames));

    // Persist the visibility map per table. Only toggleable columns live in
    // the map, so non-toggleable columns can never be stored as hidden.
    useEffect(() => {
        if (!definition.id) {
            return;
        }

        try {
            localStorage.setItem(`${STORAGE_PREFIX}${definition.id}`, JSON.stringify(columnVisibility));
        } catch {
            // Storage unavailable — visibility just won't persist.
        }
    }, [definition.id, columnVisibility]);

    const toggleColumn = (name: string, visible: boolean): void => {
        setColumnVisibility((current) => ({ ...current, [name]: visible }));
    };

    const resetColumns = (): void => {
        setColumnVisibility(() => {
            const next: VisibilityState = {};

            for (const column of definition.columns) {
                if (column.toggleable) {
                    next[column.name] = true;
                }
            }

            return next;
        });
    };

    // Debounce the search input — the endpoint is hit once the user pauses,
    // not on every keystroke. A new term re-runs the query, so jump back to
    // the first page like sorting and filtering do — but only when the
    // settled term actually changed, so hydrating a URL-provided search on
    // mount can't reset a URL-provided page.
    useEffect(() => {
        const timeout = window.setTimeout(() => {
            const next = searchInput.trim();

            if (next !== committedSearch.current) {
                committedSearch.current = next;
                setSearchTerm(next);
                setPage(1);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [searchInput]);

    // Debounce every text filter the same way. Each keystroke resets the
    // timer; once the user pauses, the inputs sync into columnFilters (empty
    // terms drop the filter entirely so no `filter[]` param is sent). The
    // fetch effect watches columnFilters, so the request fires exactly once
    // per settled term — and the page resets only when the settled values
    // actually changed, so URL hydration on mount keeps the URL page.
    useEffect(() => {
        const timeout = window.setTimeout(() => {
            const next: ColumnFiltersState = [];

            for (const name of textFilterNames) {
                const value = (textInputs[name] ?? '').trim();

                if (value !== '') {
                    next.push({ id: name, value });
                }
            }

            const signature = textFilterSignature(next, textFilterNames);

            if (signature !== committedTextFilters.current) {
                committedTextFilters.current = signature;
                setColumnFilters((current) => [
                    ...current.filter((columnFilter) => !textFilterNames.has(columnFilter.id)),
                    ...next,
                ]);
                setPage(1);
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [textInputs, textFilterNames]);

    useEffect(() => {
        const tableId = definition.id;

        if (!tableId) {
            return;
        }

        const sort = activeSort?.id ?? '';
        const direction = activeSort ? (activeSort.desc ? 'desc' : 'asc') : '';
        const search = searchTerm.trim();
        const filters = filtersSignature(columnFilters);
        const current = lastLoaded.current;

        if (
            current.page === page &&
            current.perPage === perPage &&
            current.sort === sort &&
            current.direction === direction &&
            current.search === search &&
            current.filters === filters &&
            current.group === group &&
            current.refreshKey === refreshKey
        ) {
            return;
        }

        aborters.current[tableId]?.abort();

        const controller = new AbortController();
        aborters.current[tableId] = controller;

        setIsLoading(true);

        const params = buildTableParams(page, perPage, sorting, searchTerm, columnFilters, group);

        const indexUrl = source ? source.index : panelUrl(`/table/${tableId}`);

        fetch(`${indexUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Table returned ${response.status}`);
                }

                return response.json() as Promise<TablePayload>;
            })
            .then((payload) => {
                // A newer page/sort/filter change superseded this request — drop it.
                if (aborters.current[tableId] !== controller) {
                    return;
                }

                setRows(payload.rows);
                setTotal(payload.total);
                setLastPage(payload.lastPage);
                setPerPage(payload.perPage);
                setSummary(payload.summary);
                setGroupSummary(payload.groupSummary);
                setGroup(payload.activeGroup ?? '');

                // Adopt the fetched definition (columns, actions, filters,
                // header/toolbar actions, grouping) — an embedded relation
                // table's shell starts empty, so the first fetch populates it.
                setDefinition(payload);

                // The server may have clamped the page (e.g. beyond the last
                // one) — adopt its answer so pagination stays consistent.
                setPage(payload.page);
                lastLoaded.current = { page: payload.page, perPage: payload.perPage, sort, direction, search, filters, group, refreshKey };
            })
            .catch(() => {
                // Aborted or unreachable — keep the current page's rows.
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            });
    }, [page, perPage, activeSort, searchTerm, columnFilters, refreshKey, group, definition.id, source]);

    // Mirror the view state back into the URL — the same param names as the
    // index endpoint — so the back button and shareable links restore it.
    // Defaults are omitted to keep the URL readable, and each distinct view
    // becomes a history entry via pushState (never replaceState). When the
    // URL already reflects the state (mount hydration, or a popstate we just
    // applied), nothing is pushed. An embedded relation table owns neither the
    // URL nor the history, so it skips this entirely.
    useEffect(() => {
        if (source) {
            return;
        }

        const params = buildTableParams(page, perPage, sorting, searchTerm, columnFilters, group);

        if (page === 1) {
            params.delete('page');
        }

        if (perPage === definition.recordsPerPage) {
            params.delete('perPage');
        }

        const target = `${window.location.pathname}${params.size > 0 ? `?${params}` : ''}`;

        // Order- and index-insensitive comparison: the server rebuilds filter
        // params into PHP indexed form on its initial render, and shared
        // links may arrive in any order — neither may read as a change that
        // spawns a spurious history entry on mount.
        if (canonicalParams(window.location.search) === canonicalParams(params.toString())) {
            return;
        }

        // Clone Inertia's own history state with the new URL rather than
        // pushing a foreign entry: Inertia v2 treats an entry with no `page`
        // marker as missing and forces a full server visit on back (which
        // would mangle the URL and lose state), while a clone is restored
        // client-side like any Inertia page. Fall back to a null state if the
        // current entry has no page marker to clone.
        const currentState = window.history.state;
        const hasPage = currentState !== null && typeof currentState === 'object' && 'page' in currentState;

        window.history.pushState(hasPage ? { ...currentState, url: target } : null, '', target);
    }, [page, perPage, sorting, searchTerm, columnFilters, group, definition.recordsPerPage, source, definition.id]);

    // Back/forward restore the exact view: re-derive the state from the URL
    // the browser popped to and apply it. The write-back effect then sees a
    // URL that already matches and skips pushing a duplicate entry. Embedded
    // tables never listen — they don't own the history.
    useEffect(() => {
        if (source) {
            return;
        }

        const onPopState = (): void => {
            const next = readUrlState(window.location.href, definition);

            setPage(next.page);
            setPerPage(next.perPage);
            setSorting(next.sorting);
            setColumnFilters(next.columnFilters);
            setTextInputs(next.textInputs);
            setSearchInput(next.search);
            setSearchTerm(next.search);
            setGroup(next.group || (definition.activeGroup ?? ''));
            committedSearch.current = next.search;
            committedTextFilters.current = textFilterSignature(next.columnFilters, textFilterNames);
        };

        window.addEventListener('popstate', onPopState);

        return () => window.removeEventListener('popstate', onPopState);
    }, [definition, textFilterNames]);

    const table = useReactTable({
        data: rows,
        columns,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            pagination: { pageIndex: page - 1, pageSize: perPage },
        },
        onColumnVisibilityChange: setColumnVisibility,
        manualPagination: true,
        manualSorting: true,
        // Filtering is server-side; TanStack only tracks the state and never
        // filters the loaded page client-side.
        manualFiltering: true,
        // The endpoint honors exactly one sort column — never let the UI drift
        // into a multi-sort state (TanStack's shift+click) it can't express.
        maxMultiSortColCount: 1,
        pageCount: Math.max(lastPage, 1),
        onSortingChange: (updater) => {
            const next = typeof updater === 'function' ? updater(sorting) : updater;
            setSorting(next);

            // Server-side sorting re-runs the query — jump back to the first
            // page so the user always sees the top of the new order.
            setPage(1);
        },
        onColumnFiltersChange: (updater) => {
            const next = typeof updater === 'function' ? updater(columnFilters) : updater;
            setColumnFilters(next);
            setPage(1);
        },
        onPaginationChange: (updater) => {
            const next =
                typeof updater === 'function'
                    ? updater({ pageIndex: page - 1, pageSize: perPage })
                    : updater;

            if (next.pageSize !== perPage) {
                setPerPage(next.pageSize);
                setPage(1);
            } else {
                setPage(next.pageIndex + 1);
            }
        },
        getCoreRowModel: getCoreRowModel(),
    });

    const firstRow = total === 0 ? 0 : (page - 1) * perPage + 1;
    const lastRow = Math.min(page * perPage, total);

    const filterValue = (filter: TableFilter): string | string[] => {
        const value = columnFilters.find((columnFilter) => columnFilter.id === filter.name)?.value;

        if (Array.isArray(value)) {
            return value.map(String);
        }

        return value === null || value === undefined ? '' : String(value);
    };

    const setFilterValue = (filter: TableFilter, value: string | string[]): void => {
        setColumnFilters([
            ...columnFilters.filter((columnFilter) => columnFilter.id !== filter.name),
            ...(Array.isArray(value) && value.length === 0 ? [] : [{ id: filter.name, value }]),
        ]);
    };

    // Indicators for the currently active search term and column filters,
    // derived from the *debounced* state — a pill (and the Reset button)
    // appears exactly when the matching request is in flight, never on an
    // uncommitted keystroke.
    const activeFilters = useMemo<ActiveFilter[]>(() => {
        const list: ActiveFilter[] = [];
        const term = searchTerm.trim();

        if (term !== '') {
            list.push({ kind: 'search', label: 'Search', valueLabel: term, id: '__search' });
        }

        const definitions = new Map<string, TableFilter>();

        for (const filter of definition.filters ?? []) {
            definitions.set(filter.name, filter);
        }

        for (const columnFilter of columnFilters) {
            const definition = definitions.get(columnFilter.id);

            if (definition === undefined) {
                continue;
            }

            // Select filters describe their value with the option label;
            // text filters show the raw term like the global search does.
            // A trashed filter carries options too, so it reads the same way.
            const describe = (value: string): string =>
                definition.type === 'select' || definition.type === 'trashed'
                    ? definition.options.find((option) => option.value === value)?.label ?? value
                    : value;

            if (Array.isArray(columnFilter.value)) {
                const values = columnFilter.value as string[];

                if (values.length === 0) {
                    continue;
                }

                list.push({
                    kind: 'filter',
                    id: definition.name,
                    label: definition.label,
                    valueLabel: values.map(describe).join(', '),
                });
            } else if (
                columnFilter.value !== undefined &&
                columnFilter.value !== null &&
                String(columnFilter.value) !== ''
            ) {
                list.push({
                    kind: 'filter',
                    id: definition.name,
                    label: definition.label,
                    valueLabel: describe(String(columnFilter.value)),
                });
            }
        }

        return list;
    }, [searchTerm, columnFilters, definition.filters]);

    const dismissActiveFilter = (active: ActiveFilter): void => {
        if (active.kind === 'search') {
            setSearchInput('');
            setSearchTerm('');
            committedSearch.current = '';
            setPage(1);

            return;
        }

        // Clear the live text input (if any) too, so the debounce effect
        // can't re-add the filter from the uncommitted value.
        setTextInputs((current) => {
            const next = { ...current };

            delete next[active.id];

            return next;
        });
        setColumnFilters((current) => current.filter((columnFilter) => columnFilter.id !== active.id));
        // Mark the removal as settled immediately — otherwise retyping the
        // same term within the debounce window would look like "no change".
        committedTextFilters.current = textFilterSignature(
            columnFilters.filter((columnFilter) => columnFilter.id !== active.id),
            textFilterNames,
        );
        setPage(1);
    };

    const resetFilters = (): void => {
        setSearchInput('');
        setSearchTerm('');
        setTextInputs({});
        setColumnFilters([]);
        committedSearch.current = '';
        committedTextFilters.current = '';
        setPage(1);
    };

    // The filter form's state accessors — shared by every layout the
    // FilterPanel renders in (dropdown / modal / above / below / side).
    const filterAccessors: FilterAccessors = {
        filterValue,
        setFilterValue,
        textInputs,
        setTextInput: (name, value) => setTextInputs((current) => ({ ...current, [name]: value })),
    };

    // The filter form — shared by every layout the FilterPanel renders in
    // (dropdown / modal / above / below / side). Above/below lay the fields out
    // in a responsive grid; the rest use a single column.
    const renderFilterPanel = (grid?: boolean) => (
        <FilterPanel
            filters={definition.filters ?? []}
            accessors={filterAccessors}
            activeCount={activeFilters.length}
            onReset={resetFilters}
            grid={grid}
        />
    );

    const nativeSelectClasses =
        'h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring/50';

    return (
        <div
            className={cn(
                hasFilters && !isHiddenLayout && (isBeforeLayout || isAfterLayout)
                    ? 'flex flex-col gap-4 lg:flex-row lg:items-start'
                    : undefined,
            )}
        >
            {isBeforeLayout && hasFilters && !isHiddenLayout ? (
                <aside
                    className={cn(
                        'w-full shrink-0 lg:w-72',
                        isCollapsible && !filtersExpanded && 'hidden',
                    )}
                >
                    <div className="rounded-2xl border bg-card p-5 shadow-sm">
                        {renderFilterPanel()}
                    </div>
                </aside>
            ) : null}

            <div className="min-w-0 flex-1 overflow-hidden rounded-2xl border bg-card shadow-sm">
                {definition.heading || hasHeaderActions ? (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                    {definition.heading ? (
                        <h2 className="text-base font-semibold tracking-tight text-foreground">
                            {definition.heading}
                        </h2>
                    ) : null}

                    {hasHeaderActions ? (
                        <HeaderActions
                            actions={definition.headerActions ?? []}
                            onSucceeded={() => setRefreshKey((key) => key + 1)}
                            submitUrlFor={source ? (name) => source.action(undefined, name) : undefined}
                        />
                    ) : null}
                </div>
            ) : null}

            {isAboveLayout && hasFilters && !isHiddenLayout ? (
                <div className={cn('border-b border-border bg-muted/30 px-5 py-4', isCollapsible && !filtersExpanded && 'hidden')}>
                    {renderFilterPanel(true)}
                </div>
            ) : null}

            {hasSearchableColumns || hasGroups || hasToggleableColumns || showFilterTrigger ? (
                <div className="flex flex-wrap items-center gap-3 border-b border-border bg-muted/30 px-3 py-1.5">
                    <div className="relative flex min-w-0 flex-1 items-center sm:max-w-xs">
                        <Search className="pointer-events-none absolute left-3 size-4 text-muted-foreground" aria-hidden="true" />
                        <Input
                            id="table-search"
                            type="search"
                            value={searchInput}
                            onChange={(event) => setSearchInput(event.target.value)}
                            placeholder="Search…"
                            className="pl-9 pr-8"
                        />
                        {searchInput ? (
                            <button
                                type="button"
                                onClick={() => setSearchInput('')}
                                className="absolute right-2 rounded p-0.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                aria-label="Clear search"
                            >
                                <X className="size-3.5" />
                            </button>
                        ) : null}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        {hasGroups ? (
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                <span className="font-medium">Group</span>
                                <select
                                    id="table-group"
                                    name="group"
                                    value={group}
                                    onChange={(event) => {
                                        const next = event.target.value;
                                        setGroup(next);
                                        // Grouping re-runs the query — return to the
                                        // first page so the new groups are visible.
                                        setPage(1);
                                        setCollapsedGroups(new Set());
                                    }}
                                    className={nativeSelectClasses}
                                >
                                    <option value="">None</option>
                                    {(definition.groups ?? []).map((candidate) => (
                                        <option key={candidate.column} value={candidate.column}>
                                            {candidate.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}

                        {hasToggleableColumns ? (
                            <DropdownMenu open={isColumnsOpen} onOpenChange={setIsColumnsOpen}>
                                <DropdownMenuTrigger
                                    render={
                                        <Button variant="outline" size="sm" className="gap-1.5">
                                            <Columns3 className="size-4" aria-hidden="true" />
                                            Columns
                                        </Button>
                                    }
                                />
                                <DropdownMenuContent align="end" className="w-56">
                                    <DropdownMenuGroup>
                                        <div className="flex items-center justify-between px-2 py-1.5">
                                            <DropdownMenuLabel className="px-0 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                Visible columns
                                            </DropdownMenuLabel>
                                            <Button
                                                type="button"
                                                variant="link"
                                                size="sm"
                                                className="h-auto px-2 text-xs font-medium"
                                                onClick={resetColumns}
                                            >
                                                Reset
                                            </Button>
                                        </div>
                                    </DropdownMenuGroup>
                                    <DropdownMenuSeparator />
                                    {definition.columns
                                        .filter((column) => column.toggleable)
                                        .map((column) => (
                                            <DropdownMenuCheckboxItem
                                                key={column.name}
                                                checked={columnVisibility[column.name] !== false}
                                                onCheckedChange={(checked) => toggleColumn(column.name, checked)}
                                            >
                                                {column.label}
                                            </DropdownMenuCheckboxItem>
                                        ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : null}

                        {showFilterTrigger ? (
                            filtersLayout === 'modal' ? (
                                <Dialog open={isFilterModalOpen} onOpenChange={setFilterModalOpen}>
                                    <DialogTrigger
                                        render={
                                            <Button variant="outline" size="sm" className="gap-1.5">
                                                <ListFilter className="size-4" aria-hidden="true" />
                                                Filters
                                                {activeFilters.length > 0 ? (
                                                    <span className="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                                        {activeFilters.length}
                                                    </span>
                                                ) : null}
                                            </Button>
                                        }
                                    />
                                    <DialogContent className="sm:max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>Filters</DialogTitle>
                                        </DialogHeader>
                                        {renderFilterPanel()}
                                    </DialogContent>
                                </Dialog>
                            ) : filtersLayout === 'dropdown' ? (
                                <Popover open={isFiltersOpen} onOpenChange={setIsFiltersOpen}>
                                    <PopoverTrigger
                                        render={
                                            <Button variant="outline" size="sm" className="gap-1.5">
                                                <ListFilter className="size-4" aria-hidden="true" />
                                                Filters
                                                {activeFilters.length > 0 ? (
                                                    <span className="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                                        {activeFilters.length}
                                                    </span>
                                                ) : null}
                                            </Button>
                                        }
                                    />
                                    <PopoverContent align="end" sideOffset={8} className="w-80">
                                        {renderFilterPanel()}
                                    </PopoverContent>
                                </Popover>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5"
                                    onClick={() => setFiltersExpanded((expanded) => !expanded)}
                                >
                                    <ListFilter className="size-4" aria-hidden="true" />
                                    Filters
                                    {activeFilters.length > 0 ? (
                                        <span className="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                            {activeFilters.length}
                                        </span>
                                    ) : null}
                                </Button>
                            )
                        ) : null}
                    </div>
                </div>
            ) : null}

            {activeFilters.length > 0 ? (
                <div className="flex flex-wrap items-center gap-1.5 border-b border-border bg-muted/30 px-5 py-2.5">
                    <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                        Active filters
                    </span>
                    {activeFilters.map((active) => (
                        <Badge
                            key={active.id}
                            variant="outline"
                            className="gap-1 border-primary/20 bg-primary/10 py-0.5 pl-2.5 pr-1 text-xs font-normal text-primary hover:bg-primary/10"
                        >
                            <span className="font-medium">{active.label}:</span>
                            <span className="truncate">{active.valueLabel}</span>
                            <button
                                type="button"
                                onClick={() => dismissActiveFilter(active)}
                                className="rounded-full p-0.5 text-primary/60 transition hover:bg-primary/10 hover:text-primary"
                                aria-label={`Remove ${active.label} filter`}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            ) : null}

            {definition.selectable && selectedRecords.size > 0 ? (
                <div className="flex flex-wrap items-center gap-3 border-b border-border bg-primary/5 px-3 py-1.5">
                    <span className="text-xs font-medium text-foreground">
                        <span className="text-primary">{selectedRecords.size} selected</span>
                    </span>

                    <span className="h-4 w-px bg-border" />

                    <div className="flex flex-wrap items-center gap-1">
                        {(definition.toolbarActions ?? []).map((action) => {
                            const isRunning = runningBulkAction === action.name;
                            const Icon = action.icon ? ICONS[action.icon] : undefined;
                            const content = isRunning ? (
                                <span className="size-3.5 animate-pulse rounded-full border-2 border-current border-t-transparent" />
                            ) : (
                                <>
                                    {Icon ? <Icon className="size-3.5" aria-hidden="true" /> : null}
                                    {action.label}
                                </>
                            );

                            return (
                                <Tooltip key={action.name}>
                                    <TooltipTrigger
                                        render={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={isRunning}
                                                onClick={() => {
                                                    if (action.requiresConfirmation) {
                                                        setBulkConfirm({ action });
                                                    } else {
                                                        runBulkAction(action);
                                                    }
                                                }}
                                                className={cn('gap-1.5', ACTION_COLORS[action.color ?? 'primary'])}
                                            >
                                                {content}
                                            </Button>
                                        }
                                    />
                                    <TooltipContent>{action.label}</TooltipContent>
                                </Tooltip>
                            );
                        })}
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedRecords(new Set())}
                        className="ml-auto text-muted-foreground hover:text-foreground"
                    >
                        Clear
                    </Button>
                </div>
            ) : null}

            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRowPrimitive key={headerGroup.id} className="border-border bg-muted/30">
                            {headerGroup.headers.map((header) => (
                                <TableHead
                                    key={header.id}
                                    aria-sort={
                                        header.column.getIsSorted() === 'asc'
                                            ? 'ascending'
                                            : header.column.getIsSorted() === 'desc'
                                              ? 'descending'
                                              : undefined
                                    }
                                    className="h-7 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                >
                                    {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                        <button
                                            type="button"
                                            onClick={header.column.getToggleSortingHandler()}
                                            className={`inline-flex items-center gap-1.5 transition hover:text-foreground ${
                                                header.column.getIsSorted() ? 'text-foreground' : ''
                                            }`}
                                            aria-label={`Sort by ${header.column.columnDef.header}`}
                                        >
                                            {flexRender(header.column.columnDef.header, header.getContext())}
                                            {header.column.getIsSorted() === 'asc' ? (
                                                <ArrowUp className="size-3 text-primary" aria-hidden="true" />
                                            ) : header.column.getIsSorted() === 'desc' ? (
                                                <ArrowDown className="size-3 text-primary" aria-hidden="true" />
                                            ) : (
                                                <ArrowUpDown className="size-3 text-muted-foreground/50" aria-hidden="true" />
                                            )}
                                        </button>
                                    ) : (
                                        flexRender(header.column.columnDef.header, header.getContext())
                                    )}
                                </TableHead>
                            ))}
                        </TableRowPrimitive>
                    ))}
                </TableHeader>

                <TableBody className={cn(isLoading && 'opacity-60 transition-opacity')}>
                    {table.getRowModel().rows.length === 0 ? (
                        <TableRowPrimitive>
                            <TableCell
                                colSpan={table.getVisibleLeafColumns().length}
                                className="px-5 py-10 text-center text-sm text-muted-foreground"
                            >
                                No records found
                            </TableCell>
                        </TableRowPrimitive>
                    ) : (
                        table.getRowModel().rows.map((row, index) => {
                            const record = row.original;
                            const isGrouped = groupDefinition !== undefined && record.groupTitle !== undefined;

                            // A run header (slice 2.3): rendered whenever the
                            // group value changes relative to the row before
                            // this one. The server orders rows contiguously by
                            // group, so a change marks the start of a new run.
                            const atGroupStart =
                                isGrouped &&
                                (index === 0 || table.getRowModel().rows[index - 1].original.groupTitle !== record.groupTitle);

                            const currentGroupKey = isGrouped ? String(record.groupKey ?? record.groupTitle) : '';
                            const isCollapsed = isGrouped && collapsedGroups.has(currentGroupKey);

                            // After the last row of a group run, a per-group
                            // footer subtotal (slice 2.3) renders beneath it —
                            // the grouped analogue of the whole-table footer
                            // summary, scoped to that group's rows.
                            const nextRecord = table.getRowModel().rows[index + 1]?.original;
                            const atGroupEnd =
                                isGrouped && (nextRecord === undefined || nextRecord.groupTitle !== record.groupTitle);
                            const groupCells = atGroupEnd ? groupSummary?.[currentGroupKey] : undefined;

                            // A collapsed group shows only its header — the
                            // grouped rows are hidden (pure client display
                            // state, never persisted).
                            if (isGrouped && isCollapsed) {
                                return null;
                            }

                            return (
                                <Fragment key={row.id}>
                                    {atGroupStart ? (
                                        <TableRowPrimitive className="border-y border-border bg-muted/50">
                                            <TableCell colSpan={table.getVisibleLeafColumns().length} className="px-5 py-2">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setCollapsedGroups((current) => {
                                                            const next = new Set(current);

                                                            if (next.has(currentGroupKey)) {
                                                                next.delete(currentGroupKey);
                                                            } else {
                                                                next.add(currentGroupKey);
                                                            }

                                                            return next;
                                                        });
                                                    }}
                                                    className="flex items-center gap-2 text-sm font-semibold text-foreground"
                                                >
                                                    <ChevronRight
                                                        className={cn(
                                                            'size-4 text-muted-foreground transition-transform',
                                                            isCollapsed && 'rotate-90',
                                                        )}
                                                    />
                                                    {groupDefinition?.label}: {String(record.groupTitle)}
                                                </button>
                                            </TableCell>
                                        </TableRowPrimitive>
                                    ) : null}

                                    {!isCollapsed ? (
<TableRowPrimitive
                                            className={cn('hover:bg-muted/40', record.recordUrl && 'cursor-pointer')}
                                            onClick={
                                                record.recordUrl
                                                    ? (event) => {
                                                          // Row navigation (record navigation slice):
                                                          // clicking a row opens the record (view page,
                                                          // else edit). Clicks on interactive elements
                                                          // inside the row — action buttons, selection
                                                          // checkboxes (Base UI renders them as
                                                          // [role="checkbox"]), links — never trigger it.
                                                          const target = event.target as HTMLElement;

                                                          if (target.closest('button, a, input, select, label, [role="menuitem"], [role="checkbox"]')) {
                                                              return;
                                                          }

                                                          router.visit(record.recordUrl as string);
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id} className="px-5 py-3 text-foreground">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            ))}
                                        </TableRowPrimitive>
                                    ) : null}

                                    {groupCells !== undefined && Object.keys(groupCells).length > 0 ? (
                                        <TableRowPrimitive className="border-b border-border bg-muted/30">
                                            {table.getVisibleLeafColumns().map((column) => {
                                                const cells = groupCells[column.id];

                                                return (
                                                    <TableCell
                                                        key={column.id}
                                                        className="px-5 py-2 text-sm text-foreground"
                                                    >
                                                        {cells !== undefined
                                                            ? cells.map((cell) => (
                                                                  <span key={cell.label} className="flex items-baseline justify-between gap-2">
                                                                      <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                                                          {cell.label}
                                                                      </span>
                                                                      <span className="tabular-nums">
                                                                          {cell.value === null || cell.value === undefined ? '—' : String(cell.value)}
                                                                      </span>
                                                                  </span>
                                                              ))
                                                            : null}
                                                    </TableCell>
                                                );
                                            })}
                                        </TableRowPrimitive>
                                    ) : null}
                                </Fragment>
                            );
                        })
                    )}
                </TableBody>

                {summary !== undefined && Object.keys(summary).length > 0 ? (
                    <TableFooter>
                        <TableRowPrimitive className="border-border bg-muted/40">
                            {table.getVisibleLeafColumns().map((column) => {
                                const cells = summary[column.id];

                                return (
                                    <TableCell
                                        key={column.id}
                                        className="px-5 py-3 text-sm font-semibold text-foreground"
                                    >
                                        {cells !== undefined
                                            ? cells.map((cell) => (
                                                  <span key={cell.label} className="flex items-baseline justify-between gap-2">
                                                      <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                                          {cell.label}
                                                      </span>
                                                      <span className="tabular-nums">
                                                          {cell.value === null || cell.value === undefined ? '—' : String(cell.value)}
                                                      </span>
                                                  </span>
                                              ))
                                            : null}
                                    </TableCell>
                                );
                            })}
                        </TableRowPrimitive>
                    </TableFooter>
                ) : null}
            </Table>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-3 py-1.5">
                <p className="text-xs text-muted-foreground">
                    Showing <span className="font-medium text-foreground">{firstRow}</span>–
                    <span className="font-medium text-foreground">{lastRow}</span> of{' '}
                    <span className="font-medium text-foreground">{total}</span>
                    {isLoading ? <span className="ml-2 text-primary">Loading…</span> : null}
                </p>

                <div className="flex items-center gap-3">
                    <label htmlFor="table-per-page" className="flex items-center gap-2 text-xs text-muted-foreground">
                        Rows
                        <select
                            id="table-per-page"
                            name="per_page"
                            value={perPage}
                            onChange={(event) => table.setPageSize(Number(event.target.value))}
                            className={nativeSelectClasses}
                        >
                            {definition.recordsPerPageSelectOptions.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </label>

                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage() || isLoading}
                            aria-label="Previous page"
                        >
                            <ChevronLeft className="size-4" aria-hidden="true" />
                        </Button>

                        <span className="px-2 text-xs text-muted-foreground">
                            Page <span className="font-medium text-foreground">{page}</span> of{' '}
                            <span className="font-medium text-foreground">{Math.max(lastPage, 1)}</span>
                        </span>

                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage() || isLoading}
                            aria-label="Next page"
                        >
                            <ChevronRight className="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </div>
            </div>

            {isBelowLayout && hasFilters && !isHiddenLayout ? (
                <div className="border-t border-border bg-muted/30 px-5 py-4">
                    {renderFilterPanel(true)}
                </div>
            ) : null}

            <AlertDialog
                open={confirm !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirm(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {confirm?.action.modalHeading ?? `Confirm ${confirm?.action.label ?? 'action'}`}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirm?.action.modalDescription ??
                                `This runs the “${confirm?.action.label}” action on this record. This action cannot be undone.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={runningAction !== null}
                            onClick={() => {
                                if (confirm) {
                                    runAction(confirm.row, confirm.action);
                                }
                            }}
                            className={cn(
                                confirm?.action.color === 'danger' &&
                                    'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                            )}
                        >
                            {confirm?.action.label ?? 'Confirm'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={bulkConfirm !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBulkConfirm(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {bulkConfirm?.action.modalHeading ?? `Confirm ${bulkConfirm?.action.label ?? 'action'}`}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {bulkConfirm?.action.modalDescription ??
                                `This runs the “${bulkConfirm?.action.label}” action on ${selectedRecords.size} selected records. This action cannot be undone.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={runningBulkAction !== null}
                            onClick={() => {
                                if (bulkConfirm) {
                                    runBulkAction(bulkConfirm.action);
                                }
                            }}
                            className={cn(
                                bulkConfirm?.action.color === 'danger' &&
                                    'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                            )}
                        >
                            {bulkConfirm?.action.label ?? 'Confirm'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {edit !== null && definition.id !== undefined ? (
                <ActionModal
                    action={edit.action}
                    tableId={definition.id}
                    recordId={edit.row.id}
                    open
                    onClose={() => setEdit(null)}
                    onSucceeded={() => setRefreshKey((key) => key + 1)}
                    submitUrl={source ? source.action(edit.row.id, edit.action.name) : undefined}
                    recordUrl={source ? source.record(edit.row.id) : undefined}
                />
            ) : null}
            </div>

            {isAfterLayout && hasFilters && !isHiddenLayout ? (
                <aside
                    className={cn(
                        'w-full shrink-0 lg:w-72',
                        isCollapsible && !filtersExpanded && 'hidden',
                    )}
                >
                    <div className="rounded-2xl border bg-card p-5 shadow-sm">
                        {renderFilterPanel()}
                    </div>
                </aside>
            ) : null}
        </div>
    );
}
