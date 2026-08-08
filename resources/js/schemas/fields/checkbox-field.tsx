import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { normalizeBoolean } from '@/schemas/boolean';
import type { FieldProps } from '@/schemas/registry';

/**
 * Boolean checkbox field — a thin wrapper over the vendored Checkbox
 * primitive (slice 1.4). The state is a plain boolean; `inline` renders the
 * label beside the control (Filament's signature inline boolean layout).
 */
export default function CheckboxField({ node, value, error, onChange }: FieldProps) {
    // The serialized default is false for checkboxes, so an unset value can
    // never render as an indeterminate/undefined control.
    const checked = normalizeBoolean(value, Boolean(node.default ?? false));

    const label = (
        <>
            {node.label}
            {node.required ? <span className="text-destructive"> *</span> : null}
        </>
    );

    const control = (
        <Checkbox
            id={node.name}
            name={node.name}
            checked={checked}
            onCheckedChange={(next) => onChange?.(next === true)}
            disabled={node.disabled ?? node.readOnly ?? false}
            aria-invalid={error ? true : undefined}
        />
    );

    return (
        <div>
            {node.inline ? (
                <div className="flex items-center gap-2">
                    {control}
                    <Label htmlFor={node.name} className="text-sm font-medium">
                        {label}
                    </Label>
                </div>
            ) : (
                <>
                    <Label htmlFor={node.name} className="mb-1.5 block text-sm font-medium">
                        {label}
                    </Label>
                    {control}
                </>
            )}

            {node.helperText ? <p className="mt-1.5 text-xs text-muted-foreground">{node.helperText}</p> : null}

            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
