/**
 * TypeScript mirror of the JSON contract (docs/CONTRACT.md).
 */

export const CONTRACT_VERSION = 1;

export interface SelectOption {
    value: string;
    label: string;
}

/**
 * A small action rendered in a field's label row (slice C5 — mirrors
 * Filament's `hintActions`). Server-closures never serialize; visibility is
 * the client-side `visibleWhenFilled` rule, and clicking follows `url`
 * (router visit, or a new tab when `openUrlInNewTab`).
 */
export interface HintAction {
    name: string;
    label?: string;
    icon?: string;
    tooltip?: string;
    url?: string;
    openUrlInNewTab?: boolean;
    /** Field names that must all hold a non-empty value for this action to render. */
    visibleWhenFilled?: string[];
}

export interface FieldNode {
    type: string;
    name: string;
    label?: string;
    placeholder?: string;
    helperText?: string;
    /** Short line rendered in the label row (mirrors Filament's `hint()`). */
    hint?: string;
    /** Icon + optional tooltip rendered in the label row. */
    hintIcon?: { icon: string; tooltip?: string };
    /** Small actions rendered in the label row. */
    hintActions?: HintAction[];
    default?: string | number | boolean | null;
    required?: boolean;
    validation?: string[];
    options?: SelectOption[];
    dependsOn?: string[];
    /** Sibling field names that must all be truthy for this node to render (client-side). */
    whenTruthy?: string[];
    /** Sibling field names that must all be falsy for this node to render (client-side). */
    whenFalsy?: string[];
    disabled?: boolean;
    /** Hidden entirely (incl. `hiddenOn(<operation>)` resolved server-side). */
    hidden?: boolean;
    /** Rendered read-only: value displays, cannot be edited, still submits. */
    readOnly?: boolean;
    /** Value excluded from the submit payload (rendered but never saved). */
    dehydrated?: boolean;
    autofocus?: boolean;
    maxLength?: number;
    columnSpan?: number;
    /** Textarea: number of visible rows (omitted → 3). */
    rows?: number;
    /** Checkbox/toggle/radio: label rendered beside the control instead of above. */
    inline?: boolean;
    inputType?: 'text' | 'email' | 'number' | 'password' | 'tel' | 'url' | (string & {});
    minValue?: number | string;
    maxValue?: number | string;
    /** Number inputs: the step attribute. */
    step?: number | string;
    /**
     * Arithmetic expression evaluated client-side for this field's value
     * (slice C3) — references sibling fields by name; unresolvable inputs
     * display as blank.
     */
    computed?: string;
    revealable?: boolean;
    copyable?: boolean;
    copyMessage?: string;
    telRegex?: string;
    multiple?: boolean;
    searchable?: boolean;
    /** Layout children (grid/section). */
    schema?: FieldNode[];
    /** Repeatable entry: per-item child-entry nodes (each item is a list of
     * read-only entry nodes, resolved server-side against that item's data). */
    items?: FieldNode[][];
    /** Grid: number of equal-width columns. Radio: option-grid columns. Fieldset: child grid columns. */
    columns?: number;
    /** Checkbox list: per-option description map (value => text). */
    descriptions?: Record<string, string>;
    /** Checkbox list: show select-all / deselect-all actions. */
    bulkToggleable?: boolean;
    /** Section: heading text. */
    heading?: string;
    /** Section: description text. */
    description?: string;
    /** Tabs: 1-indexed active tab (omitted → 1). */
    activeTab?: number;
    /** Date/time picker: internal PHP state format (e.g. `Y-m-d H:i:s`). */
    format?: string;
    /** Date/time picker: day.js display format (e.g. `M j, Y H:i`). */
    displayFormat?: string;
    /** Date/time picker: minimum selectable date (state-format string). */
    minDate?: string;
    /** Date/time picker: maximum selectable date (state-format string). */
    maxDate?: string;
    /** Date/time picker: disabled dates (state-format strings). */
    disabledDates?: string[];
    /** Date/time picker: first day of the week (1 = Monday … 7 = Sunday). */
    firstDayOfWeek?: number;
    /** Date/time picker: step for the hour/minute/second inputs. */
    hoursStep?: number;
    minutesStep?: number;
    secondsStep?: number;
    /** Date/time picker: IANA timezone name. */
    timezone?: string;
    /** Date/time picker: locale code. */
    locale?: string;
    /** Date/time picker: close the panel after selecting a date. */
    closeOnDateSelection?: boolean;
    /** Tags input: allow dragging tags to reorder. */
    reorderable?: boolean;
    /** Tags input: separator used to split a string initial value into tags. */
    separator?: string;
    /** Tags input: keys that finalize a tag as it's typed. */
    splitKeys?: string[];
    /** Tags input: clickable tag suggestions not already added. */
    suggestions?: string[];
    /** Tags input: label prefix/suffix rendered on each tag. */
    tagPrefix?: string;
    tagSuffix?: string;
    /** Toggle buttons: render as a joined button group. */
    grouped?: boolean;
    /** Toggle buttons: hide option labels, showing icons only. */
    hiddenButtonLabels?: boolean;
    /** Toggle buttons: per-option icon names keyed by option value. */
    icons?: Record<string, string>;
    /** Toggle buttons: per-option color names keyed by option value. */
    colors?: Record<string, string>;
    /** Toggle buttons: per-option tooltips keyed by option value. */
    tooltips?: Record<string, string>;
    /** Key value: allow adding/removing/reordering rows. */
    addable?: boolean;
    deletable?: boolean;
    /** Key value: independently toggle key/value editing. */
    editableKeys?: boolean;
    editableValues?: boolean;
    addActionLabel?: string;
    keyLabel?: string;
    valueLabel?: string;
    keyPlaceholder?: string;
    valuePlaceholder?: string;
    /** Repeater: rows the form opens with, built from the row fields' defaults. */
    defaultItems?: number;
    /** Repeater: minimum/maximum row count (enforced in add/remove + validation). */
    minItems?: number;
    maxItems?: number;
    /** Repeater: rows are collapsible; `collapsed` starts them all folded. */
    collapsible?: boolean;
    collapsed?: boolean;
    /** Repeater: columns per row (1–6, grid layout). */
    grid?: number;
    /** Repeater: per-row heading, static or a `{field}` token template. */
    itemLabel?: string;
    /** Repeater: duplicate a row. */
    cloneable?: boolean;
    /** Repeater: reordering via drag handle and/or up-down buttons. */
    reorderableWithDragAndDrop?: boolean;
    reorderableWithButtons?: boolean;
    /** Repeater: number the row headings; show/hide the per-row header bar. */
    itemNumbers?: boolean;
    itemHeaders?: boolean;
    [key: string]: unknown;
}

export interface SchemaDocument {
    id?: string;
    contract: number;
    schema: FieldNode[];
    data: Record<string, unknown>;
    errors: Record<string, string[]>;
}
