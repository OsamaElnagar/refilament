import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { normalizeBoolean } from '@/schemas/boolean';
import type { FieldProps } from '@/schemas/registry';

/**
 * Boolean toggle field — a thin wrapper over the vendored Switch primitive
 * (slice 1.4). Same contract as CheckboxField; `inline` renders the label
 * beside the switch.
 */
export default function ToggleField({ node, value, error, onChange }: FieldProps) {
    const checked = normalizeBoolean(value, Boolean(node.default ?? false));

    const label = (
        <>
            {node.label}
            {node.required ? <span className="text-destructive"> *</span> : null}
        </>
    );

    const control = (
        <Switch
            id={node.name}
            name={node.name}
            checked={checked}
            onCheckedChange={(next) => onChange?.(next)}
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
