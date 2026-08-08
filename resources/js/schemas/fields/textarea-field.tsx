import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { FieldProps } from '@/schemas/registry';

/**
 * Multi-line text field — a thin wrapper over the vendored Textarea
 * primitive (slice 1.4). Mirrors TextInputField's label/error layout; the
 * rows come from the serialized node (default 3).
 */
export default function TextareaField({ node, value, error, onChange }: FieldProps) {
    return (
        <div>
            <div className="mb-1.5 flex items-baseline justify-between gap-2">
                <Label htmlFor={node.name}>
                    {node.label}
                    {node.required ? <span className="text-destructive"> *</span> : null}
                </Label>

                {node.helperText ? (
                    <span className="text-xs text-muted-foreground">{node.helperText}</span>
                ) : null}
            </div>

            <Textarea
                id={node.name}
                name={node.name}
                rows={node.rows ?? 3}
                value={(value as string | undefined) ?? (node.default as string | undefined) ?? ''}
                onChange={(event) => onChange?.(event.target.value)}
                placeholder={node.placeholder}
                maxLength={node.maxLength}
                disabled={node.disabled ?? false}
                autoFocus={node.autofocus ?? false}
                aria-invalid={error ? true : undefined}
                className={cn(error && 'border-destructive focus-visible:ring-destructive/30')}
            />

            {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
