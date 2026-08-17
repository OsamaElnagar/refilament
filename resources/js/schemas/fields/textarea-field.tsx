import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

/**
 * Multi-line text field — a thin wrapper over the vendored Textarea
 * primitive (slice 1.4). Mirrors TextInputField's label/error layout; the
 * rows come from the serialized node (default 3).
 */
export default function TextareaField({ node, value, error, onChange, formValues }: FieldProps) {
    return (
        <div>
            <FieldHeader node={node} formValues={formValues} labelId={node.name} />

            <Textarea
                id={node.name}
                name={node.name}
                rows={node.rows ?? 3}
                value={(value as string | undefined) ?? (node.default as string | undefined) ?? ''}
                onChange={(event) => onChange?.(event.target.value)}
                placeholder={node.placeholder}
                maxLength={node.maxLength}
                disabled={node.disabled ?? node.readOnly ?? false}
                autoFocus={node.autofocus ?? false}
                aria-invalid={error ? true : undefined}
                className={cn(error && 'border-destructive focus-visible:ring-destructive/30')}
            />

            {error ? <p className="mt-0.5 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
