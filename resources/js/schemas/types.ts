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
    [key: string]: unknown;
}

export interface SchemaDocument {
    id?: string;
    contract: number;
    schema: FieldNode[];
    data: Record<string, unknown>;
    errors: Record<string, string[]>;
}
