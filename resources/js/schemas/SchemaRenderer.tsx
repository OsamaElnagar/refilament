import { useEffect, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { renderNotification } from '@/notifications/renderNotification';
import DebugField from '@/schemas/fields/debug-field';
import { getField, getLayout } from '@/schemas/registry';
import { flattenNodes } from '@/schemas/tree';
import { isNodeVisible } from '@/schemas/visibility';
import type { FieldNode } from '@/schemas/types';
import { useDebouncedValidate } from '@/schemas/useDebouncedValidate';
import { useDependentOptions } from '@/schemas/useDependentOptions';
import { useSchemaSubmit } from '@/schemas/useSchemaSubmit';

interface SchemaRendererProps {
    schema: FieldNode[];
    data: Record<string, unknown>;
    errors: Record<string, string[]>;
    schemaId?: string;
    /** When set, renders a submit footer that POSTs via the typed submit endpoint. */
    submitLabel?: string;
    /**
     * Override the submit endpoint — the edit modal (slice 1.2) submits
     * through the table action endpoint with the record id; the full-page
     * edit (slice 1.7) submits through the typed record update endpoint
     * with the record in the URL.
     */
    submitUrl?: string;
    /** The record being edited, included in the submit body as `record`. */
    submitRecord?: string | number;
    /**
     * The record lives in the submit URL (typed record update endpoint,
     * slice 1.7) — do not duplicate it in the body.
     */
    submitRecordInUrl?: boolean;
    /** Called after a successful submit (e.g. to navigate to the created record). */
    onSuccess?: () => void;
}

export default function SchemaRenderer({
    schema,
    data,
    errors: initialErrors,
    schemaId,
    submitLabel,
    submitUrl,
    submitRecord,
    submitRecordInUrl,
    onSuccess,
}: SchemaRendererProps) {
    const [values, setValues] = useState<Record<string, unknown>>(data);

    // Server-side validation errors, mapped back onto the fields they name
    // (docs/CONTRACT.md, "Form submission"). Rules are server-authoritative;
    // the client's serialized copy is a hint only.
    const {
        submitting,
        errors: submitErrors,
        submitError,
        successMessage,
        successNotification,
        submit,
        clearFieldError,
    } = useSchemaSubmit(schemaId, {
        endpoint: submitUrl,
        record: submitRecord,
        recordInUrl: submitRecordInUrl,
    });

    // Surface the submit success through sonner as well as the inline banner
    // — the banner is the parity-safe surface, the toast is the new one. A
    // configured success notification (slice 3.4) renders the rich toast;
    // otherwise the flat message fires as before.
    useEffect(() => {
        if (successNotification) {
            renderNotification(successNotification);
        } else if (successMessage) {
            renderNotification({ title: successMessage });
        }
    }, [successMessage, successNotification]);

    // Page-provided errors (e.g. from a previous render) merge under fresh
    // server errors; clearing a field's error as its value changes keeps the
    // form feeling live without ever trusting the client to validate.
    //
    // Dependent-options watching needs every node in the tree — a `dependsOn`
    // field can live at any nesting depth inside layouts. Unique-rule fields
    // are additionally live-validated debounced on change (slice 2.5).
    const allNodes = useMemo(() => flattenNodes(schema), [schema]);
    const { optionsByField, loadingFields } = useDependentOptions(schemaId, allNodes, values);
    const { errors: liveErrors, checking } = useDebouncedValidate(schemaId, allNodes, values, {
        record: submitRecord,
    });

    const fieldErrors = useMemo(
        () => ({ ...initialErrors, ...submitErrors, ...liveErrors }),
        [initialErrors, submitErrors, liveErrors],
    );

    // Names of every field whose client-side visibility rules currently hide
    // it. Used to strip them from the submit payload (schema-validation stays
    // server-authoritative; hidden values are simply never sent).
    const hiddenFieldNamesSet = useMemo(
        () => new Set(allNodes.filter((node) => !isNodeVisible(node, values)).map((node) => node.name)),
        [allNodes, values],
    );

    const handleChange = (name: string) => (value: unknown): void => {
        setValues((current) => ({ ...current, [name]: value }));
        clearFieldError(name);
    };

    const handleSubmit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
        event.preventDefault();

        // Fields hidden by client-side visibility rules carry no value worth
        // validating — strip them so the server never sees a stale value
        // (a conditionally-hidden field with a `required` rule is a developer
        // bug: required + possibly-hidden stays contradictory).
        const visibleData = Object.fromEntries(
            Object.entries(values).filter(([name]) => !hiddenFieldNamesSet.has(name)),
        );

        const succeeded = await submit(visibleData);

        if (succeeded) {
            onSuccess?.();
        }
    };

    const renderChildren = (nodes: FieldNode[]): ReactNode =>
        nodes.filter((node) => isNodeVisible(node, values)).map(renderNode);

    const renderNode = (node: FieldNode, index: number): ReactNode => {
        const Layout = getLayout(node.type);

        if (Layout) {
            return (
                <Layout
                    key={node.name ?? `${node.type}-${index}`}
                    node={node}
                    renderChildren={renderChildren}
                />
            );
        }

        const Field = getField(node.type) ?? DebugField;

        return (
            <Field
                key={node.name}
                node={node}
                value={values[node.name]}
                error={fieldErrors[node.name]?.[0]}
                options={optionsByField[node.name]}
                loading={loadingFields[node.name]}
                checking={checking[node.name]}
                onChange={handleChange(node.name)}
            />
        );
    };

    const fields = <div className="space-y-6">{renderChildren(schema)}</div>;

    if (!submitLabel) {
        return fields;
    }

    return (
        <form className="space-y-6" onSubmit={handleSubmit} noValidate>
            {fields}

            {submitError ? (
                <div
                    role="alert"
                    className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4 shrink-0" aria-hidden="true">
                        <path
                            fillRule="evenodd"
                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
                            clipRule="evenodd"
                        />
                    </svg>
                    {submitError}
                </div>
            ) : null}

            {successMessage ? (
                <div
                    role="status"
                    className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-700"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4 shrink-0" aria-hidden="true">
                        <path
                            fillRule="evenodd"
                            d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
                            clipRule="evenodd"
                        />
                    </svg>
                    {successMessage}
                </div>
            ) : null}

            <div className="flex justify-end border-t border-border pt-4">
                <Button type="submit" disabled={submitting} className="gap-2">
                    {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
