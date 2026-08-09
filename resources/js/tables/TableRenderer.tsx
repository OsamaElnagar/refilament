import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import type {
    CellContext,
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Columns3, Search, X } from 'lucide-react';
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
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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

    const actionsById = useMemo(() => {
        const map = new Map<string, TableAction>();

        for (const action of initial.actions ?? []) {
            map.set(action.name, action);
        }

        return map;
    }, [initial.actions]);

    const runAction = async (row: TableRow, action: TableAction): Promise<void> => {
        const tableId = initial.id;

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
        const tableId = initial.id;

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

    const columns = useMemo<ColumnDef<TableRow>[]>(
        () => [
            ...(initial.selectable
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
            ...initial.columns.map((column) => ({
                accessorKey: column.name,
                header: column.label,
                enableSorting: column.sortable ?? false,
                cell: (info: CellContext<TableRow, unknown>) => {
                    return <Cell value={info.getValue()} placeholder={column.placeholder} />;
                },
            })),
            ...((initial.actions?.length ?? 0) > 0
                ? [
                      {
                          id: 'actions',
                          header: '',
                          enableSorting: false,
                          cell: (info: CellContext<TableRow, unknown>) => {
                              const row = info.row.original;
                              const visible = (row.actions ?? [])
                                  .map((name) => actionsById.get(name))
                                  .filter((action): action is TableAction => action !== undefined);

                              if (visible.length === 0) {
                                  return null;
                              }

                              return (
                                  <div className="flex items-center justify-end gap-1">
                                      {visible.map((action) => {
                                          const isRunning = runningAction === `${row.id}:${action.name}`;

                                          return (
                                              <Button
                                                  key={action.name}
                                                  type="button"
                                                  variant="ghost"
                                                  size="sm"
                                                  disabled={isRunning}
                                                  onClick={() => {
                                                      if (action.type === 'edit') {
                                                          // Modal edit (slice 1.2): the shared ActionModal
                                                          // fetches the record's values + the form document,
                                                          // and submits through this action's endpoint.
                                                          setEdit({ row, action });
                                                      } else if (action.requiresConfirmation) {
                                                          setConfirm({ row, action });
                                                      } else {
                                                          runAction(row, action);
                                                      }
                                                  }}
                                                  className={ACTION_COLORS[action.color ?? 'primary']}
                                              >
                                                  {isRunning ? 'Working…' : action.label}
                                              </Button>
                                          );
                                      })}
                                  </div>
                              );
                          },
                      },
                  ]
                : []),
        ],
        [initial.columns, initial.actions, initial.selectable, actionsById, runningAction, rows, selectedRecords],
    );

    const activeSort = sorting[0];

    const hasSearchableColumns = initial.columns.some((column) => column.searchable ?? false);
    const hasFilters = (initial.filters?.length ?? 0) > 0;
    const hasToggleableColumns = initial.columns.some((column) => column.toggleable ?? false);
    const hasHeaderActions = (initial.headerActions?.length ?? 0) > 0;
    const hasGroups = (initial.groups ?? []).length > 0;

    const groupDefinition = useMemo<TableGroup | undefined>(
        () => initial.groups?.find((candidate) => candidate.column === group),
        [initial.groups, group],
    );

    const textFilterNames = useMemo(() => {
        const names = new Set<string>();

        for (const filter of initial.filters ?? []) {
            if (filter.type === 'text') {
                names.add(filter.name);
            }
        }

        return names;
    }, [initial.filters]);

    // The last *settled* search term and text-filter values. The debounce
    // effects only reset the page when these actually change, so hydrating
    // state from a shared URL on mount can't clobber the URL-provided page.
    const committedSearch = useRef(initialUrlState.search);
    const committedTextFilters = useRef(textFilterSignature(initialUrlState.columnFilters, textFilterNames));

    // Persist the visibility map per table. Only toggleable columns live in
    // the map, so non-toggleable columns can never be stored as hidden.
    useEffect(() => {
        if (!initial.id) {
            return;
        }

        try {
            localStorage.setItem(`${STORAGE_PREFIX}${initial.id}`, JSON.stringify(columnVisibility));
        } catch {
            // Storage unavailable — visibility just won't persist.
        }
    }, [initial.id, columnVisibility]);

    const toggleColumn = (name: string, visible: boolean): void => {
        setColumnVisibility((current) => ({ ...current, [name]: visible }));
    };

    const resetColumns = (): void => {
        setColumnVisibility(() => {
            const next: VisibilityState = {};

            for (const column of initial.columns) {
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
        const tableId = initial.id;

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
    }, [page, perPage, activeSort, searchTerm, columnFilters, refreshKey, group, initial.id, source]);

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

        if (perPage === initial.recordsPerPage) {
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
    }, [page, perPage, sorting, searchTerm, columnFilters, group, initial.recordsPerPage, source, initial.id]);

    // Back/forward restore the exact view: re-derive the state from the URL
    // the browser popped to and apply it. The write-back effect then sees a
    // URL that already matches and skips pushing a duplicate entry. Embedded
    // tables never listen — they don't own the history.
    useEffect(() => {
        if (source) {
            return;
        }

        const onPopState = (): void => {
            const next = readUrlState(window.location.href, initial);

            setPage(next.page);
            setPerPage(next.perPage);
            setSorting(next.sorting);
            setColumnFilters(next.columnFilters);
            setTextInputs(next.textInputs);
            setSearchInput(next.search);
            setSearchTerm(next.search);
            setGroup(next.group || (initial.activeGroup ?? ''));
            committedSearch.current = next.search;
            committedTextFilters.current = textFilterSignature(next.columnFilters, textFilterNames);
        };

        window.addEventListener('popstate', onPopState);

        return () => window.removeEventListener('popstate', onPopState);
    }, [initial, textFilterNames]);

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

    const filterSelected = (filter: TableFilter, optionValue: string): boolean => {
        const value = filterValue(filter);

        return Array.isArray(value) ? value.includes(optionValue) : value === optionValue;
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

        for (const filter of initial.filters ?? []) {
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
    }, [searchTerm, columnFilters, initial.filters]);

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

    const nativeSelectClasses =
        'h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring/50';

    return (
        <div className="overflow-hidden rounded-2xl border bg-card shadow-sm">
            {initial.heading || hasHeaderActions ? (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                    {initial.heading ? (
                        <h2 className="text-base font-semibold tracking-tight text-foreground">
                            {initial.heading}
                        </h2>
                    ) : null}

                    {hasHeaderActions ? (
                        <HeaderActions
                            actions={initial.headerActions ?? []}
                            onSucceeded={() => setRefreshKey((key) => key + 1)}
                            submitUrlFor={source ? (name) => source.action(undefined, name) : undefined}
                        />
                    ) : null}
                </div>
            ) : null}

            {hasSearchableColumns || hasFilters || hasToggleableColumns ? (
                <div className="flex flex-wrap items-center gap-3 border-b border-border bg-muted/30 px-5 py-3">
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
                                    {(initial.groups ?? []).map((candidate) => (
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
                                    <DropdownMenuSeparator />
                                    {initial.columns
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

                        {initial.filters?.map((filter) =>
                            filter.type === 'text' ? (
                                <label key={filter.name} className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span className="font-medium">{filter.label}</span>
                                    <Input
                                        id={`table-filter-${filter.name}`}
                                        type="search"
                                        value={textInputs[filter.name] ?? ''}
                                        onChange={(event) =>
                                            setTextInputs((current) => ({ ...current, [filter.name]: event.target.value }))
                                        }
                                        placeholder={filter.placeholder ?? `Filter by ${filter.label.toLowerCase()}…`}
                                        className="h-8 w-40 px-2 text-xs"
                                    />
                                </label>
                            ) : filter.type === 'select' && filter.multiple ? (
                                <fieldset key={filter.name} className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <legend className="font-medium">{filter.label}</legend>
                                    {filter.options.map((option) => (
                                        <label key={option.value} className="flex cursor-pointer items-center gap-1.5">
                                            <Checkbox
                                                checked={filterSelected(filter, option.value)}
                                                onCheckedChange={(checked) => {
                                                    const current = filterValue(filter);
                                                    const currentValues = Array.isArray(current)
                                                        ? current
                                                        : current
                                                          ? [current]
                                                          : [];

                                                    const next = checked
                                                        ? [...currentValues, option.value]
                                                        : currentValues.filter((value) => value !== option.value);

                                                    setFilterValue(filter, next);
                                                }}
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </fieldset>
                            ) : (
                                <label key={filter.name} className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span className="font-medium">{filter.label}</span>
                                    <select
                                        id={`table-filter-${filter.name}`}
                                        value={filterValue(filter) as string}
                                        onChange={(event) => setFilterValue(filter, event.target.value)}
                                        className={nativeSelectClasses}
                                    >
                                        {filter.options.some((option) => option.value === '') ? null : (
                                            <option value="">All</option>
                                        )}
                                        {filter.options.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ),
                        )}

                        {activeFilters.length > 0 ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={resetFilters}
                                className="gap-1.5 text-muted-foreground hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                            >
                                <X className="size-3.5" aria-hidden="true" />
                                Reset filters
                                <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                    {activeFilters.length}
                                </span>
                            </Button>
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

            {initial.selectable && selectedRecords.size > 0 ? (
                <div className="flex flex-wrap items-center gap-3 border-b border-border bg-primary/5 px-5 py-2.5">
                    <span className="text-xs font-medium text-foreground">
                        <span className="text-primary">{selectedRecords.size} selected</span>
                    </span>

                    <span className="h-4 w-px bg-border" />

                    <div className="flex flex-wrap items-center gap-1">
                        {(initial.toolbarActions ?? []).map((action) => {
                            const isRunning = runningBulkAction === action.name;

                            return (
                                <Button
                                    key={action.name}
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
                                    className={ACTION_COLORS[action.color ?? 'primary']}
                                >
                                    {isRunning ? 'Working…' : action.label}
                                </Button>
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
                                    className="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
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
<TableRowPrimitive className="hover:bg-muted/40">
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

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
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
                            {initial.recordsPerPageSelectOptions.map((option) => (
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
                        <AlertDialogTitle>Confirm {confirm?.action.label ?? 'action'}</AlertDialogTitle>
                        <AlertDialogDescription>
                            This runs the “{confirm?.action.label}” action on this record. This action cannot be
                            undone.
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
                        <AlertDialogTitle>Confirm {bulkConfirm?.action.label ?? 'action'}</AlertDialogTitle>
                        <AlertDialogDescription>
                            This runs the “{bulkConfirm?.action.label}” action on {selectedRecords.size} selected
                            records. This action cannot be undone.
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

            {edit !== null && initial.id !== undefined ? (
                <ActionModal
                    action={edit.action}
                    tableId={initial.id}
                    recordId={edit.row.id}
                    open
                    onClose={() => setEdit(null)}
                    onSucceeded={() => setRefreshKey((key) => key + 1)}
                    submitUrl={source ? source.action(edit.row.id, edit.action.name) : undefined}
                    recordUrl={source ? source.record(edit.row.id) : undefined}
                />
            ) : null}
        </div>
    );
}
