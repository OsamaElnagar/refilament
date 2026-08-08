/**
 * TypeScript mirror of the table payload contract (docs/CONTRACT.md, "Tables").
 */

export interface TableColumn {
    name: string;
    label: string;
    placeholder?: string;
    /** Omitted when false — only sortable columns carry the flag. */
    sortable?: boolean;
    /** Omitted when false — only searchable columns carry the flag. */
    searchable?: boolean;
    /** Omitted when false — only toggleable columns carry the flag. */
    toggleable?: boolean;
    /** Omitted when false — value renders as a Badge primitive. */
    badge?: boolean;
    /** Omitted when the column never links out. */
    url?: boolean;
    openUrlInNewTab?: boolean;
    /** Present when the column declares footer summaries (slice 1.7). */
    summarized?: boolean;
}

/**
 * One footer summary cell (slice 1.7) — a server-computed aggregate over
 * the table's filtered query, keyed by column name on the payload.
 */
export interface TableSummary {
    label: string;
    value: string | number | null;
}

export type TableSummaryMap = Record<string, TableSummary[]>;

export interface TableFilterOption {
    value: string;
    label: string;
}

interface TableFilterBase {
    name: string;
    label: string;
}

/** Discrete value filter — sent as `filter[<name>]=<value>`, matched exactly. */
export interface TableSelectFilter extends TableFilterBase {
    type: 'select';
    options: TableFilterOption[];
    /** Omitted unless the filter accepts several values at once. */
    multiple?: boolean;
}

/** Free-text filter — sent as `filter[<name>]=<term>`, matched with LIKE. */
export interface TableTextFilter extends TableFilterBase {
    type: 'text';
    /** Input placeholder, omitted unless configured. */
    placeholder?: string;
}

/**
 * Soft-delete view filter — sent as `filter[<name>]=<value>` with one of
 * `with` (all), `only` (trashed), or `''` (only live, the default). Renders
 * as a single-select; the options are provided by the server.
 */
export interface TableTrashedFilter extends TableFilterBase {
    type: 'trashed';
    options: TableFilterOption[];
    /** Input placeholder, omitted unless configured. */
    placeholder?: string;
}

export type TableFilter = TableSelectFilter | TableTextFilter | TableTrashedFilter;

export type TableActionColor = 'primary' | 'secondary' | 'danger' | 'success' | 'warning' | 'info';

export type TableActionType = 'create' | 'edit' | 'delete';

export interface TableAction {
    name: string;
    label: string;
    /** Omitted unless configured. */
    color?: TableActionColor;
    /**
     * The modal behavior (header actions only): 'create' opens the linked
     * form in a dialog; 'edit'/'delete' land with slice 1.2. Row actions
     * carry no type — they run inline through the typed action endpoint.
     */
    type?: TableActionType;
    /**
     * The form schema document id a modal action hosts — fetched from the
     * typed document endpoint and submitted through the typed submit
     * endpoint (docs/CONTRACT.md, "Modal actions").
     */
    schema?: string;
    /** Omitted unless the action asks for confirmation first. */
    requiresConfirmation?: boolean;
}

/**
 * A toolbar (bulk) action (slice 2.2) — runs once against every selected
 * record through the typed bulk endpoint (docs/CONTRACT.md, "Bulk actions").
 * Only the display surface matters here: the server executes the behavior
 * against the set of selected records.
 */
export interface TableBulkAction {
    name: string;
    label: string;
    /** Omitted unless configured. */
    color?: TableActionColor;
    /** Omitted unless the action asks for confirmation first. */
    requiresConfirmation?: boolean;
}

/**
 * A record grouping (slice 2.3) — splits the table into runs by a shared
 * column value, each with a header row. Ships at the table level; the active
 * one is chosen by the `group` query param (falling back to the table's
 * default group).
 */
export interface TableGroup {
    /** The column whose value keys the grouping. */
    column: string;
    /** Header label for the group selector. */
    label: string;
    /** Whether the client may collapse groups to their header (pure client state). */
    collapsible?: boolean;
}

export interface TableRow {
    /** The record's primary key, serialized as-is. PHP's json_encode turns
     * numeric-string keys back into JSON numbers, so accept both. */
    id: string | number;
    /** The value this row is grouped under (slice 2.3) — present only when a
     * group is active. The value the group header labels. */
    groupKey?: string | number;
    /** The rendered group-header label for this row (slice 2.3). */
    groupTitle?: string;
    /** Names of the actions visible for this record; omitted when the table
     * defines no actions. Definitions live on the payload. */
    actions?: string[];
    /** Column cells. Plain columns ship a scalar (string | number | null); a
     * column with display concerns (badge / color / icon / url) ships this
     * structured object so its presentation resolves server-side per record. */
    [key: string]: unknown;
}

/**
 * A structured table cell — the per-record display of a column that carries
 * presentation concerns (Slice 2.1). Plain columns ship a bare scalar instead
 * of this object; the renderer normalizes both shapes via cellValue().
 */
export interface TableCellDisplay {
    /** The formatted display value (may be null for empty cells). */
    value: string | number | null;
    /** Render the value as a Badge primitive. */
    badge?: boolean;
    /** Badge/text color name (resolved server-side per record). */
    color?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info';
    /** Icon key rendered beside the value. */
    icon?: string;
    /** Icon color (only when the icon has its own color). */
    iconColor?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info';
    /** Link the cell to a url (resolved server-side per record). */
    url?: string;
    openUrlInNewTab?: boolean;
}

export interface TablePayload {
    id?: string;
    heading?: string;
    /** Page-only: a model-derived title (e.g. "User") for the header create action — never on the JSON endpoint. */
    resourceTitle?: string;
    columns: TableColumn[];
    /** Omitted when the table defines no filters. */
    filters?: TableFilter[];
    /** Omitted when the table defines no row actions. */
    actions?: TableAction[];
    /** Omitted when the table defines no header actions (e.g. modal create). */
    headerActions?: TableAction[];
    /** Present only when the table enables record selection (slice 2.2). */
    selectable?: boolean;
    /** Omitted when the table defines no toolbar (bulk) actions. */
    toolbarActions?: TableBulkAction[];
    /** Available groupings (slice 2.3) — present only when groups are registered. */
    groups?: TableGroup[];
    /** The active grouping column (slice 2.3); resolved server-side from the
     * `group` param or the table's default group. Omitted when ungrouped. */
    activeGroup?: string;
    recordsPerPage: number;
    recordsPerPageSelectOptions: number[];
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
    rows: TableRow[];
    /** Footer summaries (slice 1.7), keyed by column name — present only when
     * columns declare summarizers. Computed over the filtered query. */
    summary?: TableSummaryMap;
    /** Per-group footer subtotals (slice 2.3), keyed by group key — one
     * summary map per visible group. Present only when a group is active and
     * columns declare summarizers. */
    groupSummary?: Record<string, TableSummaryMap>;
}
