/**
 * TypeScript mirror of the JSON contract (docs/CONTRACT.md).
 */

export const CONTRACT_VERSION = 1;

export interface SelectOption {
    value: string;
    label: string;
}

export interface FieldNode {
    type: string;
    name: string;
    label?: string;
    placeholder?: string;
    helperText?: string;
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
