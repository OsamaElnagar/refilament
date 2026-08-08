import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { FieldProps } from '@/schemas/registry';

// Tailwind v4 scans source files for literal class names, so the dynamic
// `grid-cols-N` values live in a lookup table (mirroring grid-layout). The
// PHP builder clamps to the same 1..6 domain.
const GRID_COLS: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
    5: 'grid-cols-5',
    6: 'grid-cols-6',
};

/**
 * Radio group field — a thin wrapper over native radio inputs (slice 1.5).
 * Options share Select's contract shape; `inline` lays them out beside the
 * label, `columns` arranges them in a grid. The native radios are tinted
 * with the primary accent so they sit naturally beside the shadcn controls.
 */
export default function RadioField({ node, value, error, onChange }: FieldProps) {
    const options = node.options ?? [];
    const selected = String(value ?? node.default ?? '');
    const columns = Math.min(Math.max(node.columns ?? 1, 1), 6);
    const gridClass = GRID_COLS[columns] ?? 'grid-cols-1';

    const label = (
        <>
            {node.label}
            {node.required ? <span className="text-destructive"> *</span> : null}
        </>
    );

    return (
        <div>
            {node.inline ? (
                <div className="flex items-start gap-2">
                    <span className="pt-0.5 text-sm font-medium">{label}</span>
                    <fieldset role="radiogroup" aria-invalid={error ? true : undefined} className="flex-1">
                        <div className={cn('grid gap-x-6 gap-y-1.5', gridClass)}>
                            {options.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                >
                                    <input
                                        type="radio"
                                        name={node.name}
                                        value={option.value}
                                        checked={selected === option.value}
                                        onChange={() => onChange?.(option.value)}
                                        disabled={node.disabled ?? false}
                                        aria-invalid={error ? true : undefined}
                                        className="size-4 accent-primary"
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                </div>
            ) : (
                <fieldset role="radiogroup" aria-invalid={error ? true : undefined}>
                    <Label className="mb-1.5 block text-sm font-medium">{label}</Label>

                    <div className={cn('grid gap-x-6 gap-y-1.5', gridClass)}>
                        {options.map((option) => (
                            <label
                                key={option.value}
                                className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                            >
                                <input
                                    type="radio"
                                    name={node.name}
                                    value={option.value}
                                    checked={selected === option.value}
                                    onChange={() => onChange?.(option.value)}
                                    disabled={node.disabled ?? false}
                                    aria-invalid={error ? true : undefined}
                                    className="size-4 accent-primary"
                                />
                                {option.label}
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}

            {node.helperText ? <p className="mt-1.5 text-xs text-muted-foreground">{node.helperText}</p> : null}

            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
