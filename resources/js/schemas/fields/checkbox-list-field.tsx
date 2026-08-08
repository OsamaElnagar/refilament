import { useMemo, useState } from 'react';

import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { FieldProps } from '@/schemas/registry';

// Tailwind v4 scans source files for literal class names, so the dynamic
// `grid-cols-N` values live in a lookup table (mirroring radio-field and
// grid-layout). The PHP builder clamps to the same 1..6 domain.
const GRID_COLS: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
    5: 'grid-cols-5',
    6: 'grid-cols-6',
};

/**
 * Checkbox list field — a multi-select rendered as a checkbox group (slice).
 *
 * The value is an array of selected option values. Options use the shared
 * Select/Radio contract shape; `searchable()` filters client-side, `columns()`
 * lays the options out in a grid, `descriptions()` adds helper text per option
 * and `bulkToggleable()` shows select-all / deselect-all actions. All of this
 * is client state — there is no server round-trip for selection or search.
 */
export default function CheckboxListField({ node, value, error, onChange }: FieldProps) {
    const options = node.options ?? [];
    const descriptions = node.descriptions ?? {};
    const columns = Math.min(Math.max(node.columns ?? 1, 1), 6);
    const gridClass = GRID_COLS[columns] ?? 'grid-cols-1';
    const searchable = node.searchable ?? false;
    const bulkToggleable = node.bulkToggleable ?? false;
    const disabled = node.disabled ?? node.readOnly ?? false;

    const selected = useMemo(
        () => new Set((Array.isArray(value) ? value : []).map(String)),
        [value],
    );

    const [query, setQuery] = useState('');

    const visibleOptions = useMemo(() => {
        const q = query.trim().toLowerCase();

        return q ? options.filter((option) => option.label.toLowerCase().includes(q)) : options;
    }, [options, query]);

    const toggle = (optionValue: string): void => {
        const next = new Set(selected);

        if (next.has(optionValue)) {
            next.delete(optionValue);
        } else {
            next.add(optionValue);
        }

        onChange?.(Array.from(next));
    };

    const allSelected = options.length > 0 && options.every((option) => selected.has(option.value));

    const setAll = (checked: boolean): void => {
        onChange?.(checked ? options.map((option) => option.value) : []);
    };

    const label = (
        <>
            {node.label}
            {node.required ? <span className="text-destructive"> *</span> : null}
        </>
    );

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <Label className="text-sm font-medium">{label}</Label>

                {bulkToggleable ? (
                    <div className="flex items-center gap-1.5 text-xs">
                        <button
                            type="button"
                            onClick={() => setAll(true)}
                            disabled={disabled || options.length === 0 || allSelected}
                            className="rounded text-muted-foreground transition hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                        >
                            Select all
                        </button>
                        <span aria-hidden="true" className="text-muted-foreground/50">
                            ·
                        </span>
                        <button
                            type="button"
                            onClick={() => setAll(false)}
                            disabled={disabled || selected.size === 0}
                            className="rounded text-muted-foreground transition hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                        >
                            Deselect all
                        </button>
                    </div>
                ) : null}
            </div>

            {node.helperText ? (
                <p className="mt-1.5 text-xs text-muted-foreground">{node.helperText}</p>
            ) : null}

            {searchable ? (
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search options…"
                    className="mt-2 h-8 text-sm"
                />
            ) : null}

            {visibleOptions.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">No results</p>
            ) : (
                <div
                    role="group"
                    aria-invalid={error ? true : undefined}
                    className={cn('mt-2 grid gap-x-6 gap-y-2', gridClass)}
                >
                    {visibleOptions.map((option) => (
                        <label
                            key={option.value}
                            className="flex cursor-pointer items-start gap-2 text-sm text-foreground"
                        >
                            <Checkbox
                                checked={selected.has(option.value)}
                                onCheckedChange={() => toggle(option.value)}
                                disabled={disabled}
                                className="mt-0.5"
                            />
                            <span>
                                <span className="font-medium leading-none">{option.label}</span>
                                {descriptions[option.value] ? (
                                    <span className="mt-1 block text-xs leading-snug text-muted-foreground">
                                        {descriptions[option.value]}
                                    </span>
                                ) : null}
                            </span>
                        </label>
                    ))}
                </div>
            )}

            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}