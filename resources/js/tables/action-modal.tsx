import { useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import SchemaRenderer from '@/schemas/SchemaRenderer';
import { CONTRACT_VERSION } from '@/schemas/types';
import type { SchemaDocument } from '@/schemas/types';
import type { TableAction } from '@/tables/types';

interface ActionModalProps {
    action: TableAction;
    open: boolean;
    onClose: () => void;
    /**
     * The record being edited. When set, the record's values are fetched
     * from the typed record endpoint and the form submits through the table
     * action endpoint (with the record id) instead of the schema submit
     * endpoint.
     */
    recordId?: string | number;
    /** Required when recordId is set — the table the action runs against. */
    tableId?: string;
    /** Called after a successful submit so the table refetches. */
    onSucceeded: () => void;
    /**
     * Override the submit endpoint (slice 1.8 — a relation-scoped modal, e.g.
     * hosting a relation manager's table on a record page, submits to the
     * manager's action route rather than this table's). Used for BOTH create
     * and edit: for edit the record id is still sent alongside.
     */
    submitUrl?: string;
    /**
     * Override the record pre-fill endpoint (a relation-scoped pre-fill). The
     * form schema id is appended as the `schema` query param. Ignored when
     * recordId is unset.
     */
    recordUrl?: string;
}

/**
 * Hosts a form in a Dialog for a modal table action (slices 1.1/1.2). The
 * linked schema document is fetched on open (skeletons while loading, retry
 * on failure); with a recordId the record's values are fetched alongside and
 * pre-filled. Submission goes through the typed submit endpoint (create) or
 * the table action endpoint (edit, with `record` in the body) — both
 * validate server-side and map 422 errors back onto the fields.
 */
export default function ActionModal({ action, open, onClose, onSucceeded, recordId, tableId, submitUrl, recordUrl }: ActionModalProps) {
    const schemaId = action.schema;

    // Bumped on every open so the fetches restart and the form remounts with
    // fresh values — stale validation errors can never leak into a new form.
    const [session, setSession] = useState(0);
    const [document, setDocument] = useState<SchemaDocument | null>(null);
    const [recordData, setRecordData] = useState<Record<string, unknown> | null>(null);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [retry, setRetry] = useState(0);

    // The deferred close after a successful submit. Kept in a ref so a stale
    // timer can never close a *reopened* dialog, and cleared on unmount.
    const closeTimer = useRef<number | null>(null);

    useEffect(() => {
        if (open) {
            setSession((current) => current + 1);
        }
    }, [open]);

    // Fetches exactly once per open: `open` gates the effect (via the guard)
    // but is deliberately not a dependency — the session bump above is what
    // re-triggers it, so the first render of an open can't start a second,
    // immediately-cancelled request.
    useEffect(() => {
        if (!open || schemaId === undefined) {
            return;
        }

        let cancelled = false;

        setLoading(true);
        setLoadError(false);
        setDocument(null);
        setRecordData(null);

        const documentRequest = fetch(`/refilament/schema/${encodeURIComponent(schemaId)}`, {
            headers: { Accept: 'application/json' },
        }).then(async (response) => {
            if (!response.ok) {
                throw new Error(`Schema returned ${response.status}`);
            }

            return response.json() as Promise<SchemaDocument>;
        });

        const recordRequest =
            recordId !== undefined && tableId !== undefined
                ? fetch(
                      `${recordUrl ?? `/refilament/table/${tableId}/record/${String(recordId)}`}?schema=${encodeURIComponent(schemaId)}`,
                      { headers: { Accept: 'application/json' } },
                  ).then(async (response) => {
                      if (!response.ok) {
                          throw new Error(`Record returned ${response.status}`);
                      }

                      return response.json() as Promise<{ data: Record<string, unknown> }>;
                  })
                : Promise.resolve(null);

        Promise.all([documentRequest, recordRequest])
            .then(([doc, record]) => {
                if (!cancelled) {
                    setDocument(doc);
                    setRecordData(record?.data ?? null);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadError(true);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [session, retry, schemaId, recordId, tableId]);

    const close = useCallback((): void => {
        if (closeTimer.current !== null) {
            window.clearTimeout(closeTimer.current);
            closeTimer.current = null;
        }

        onClose();
    }, [onClose]);

    const handleSucceeded = useCallback((): void => {
        onSucceeded();

        // Let SchemaRenderer's success toast render before the dialog closes —
        // its effect fires on the next render after the success message is
        // set, and unmounting it in the same update would skip the toast.
        closeTimer.current = window.setTimeout(close, 250);
    }, [onSucceeded, close]);

    // A pending close timer must never fire on an unmounted component (or
    // after a navigation) — clear it.
    useEffect(() => {
        return () => {
            if (closeTimer.current !== null) {
                window.clearTimeout(closeTimer.current);
            }
        };
    }, []);

    const doc = document;
    const contractMismatch = doc !== null && doc.contract !== CONTRACT_VERSION;
    const isEdit = recordId !== undefined && tableId !== undefined;

    return (
        <Dialog open={open} onOpenChange={(isOpen) => { if (!isOpen) { close(); } }}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{action.label}</DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'The form is pre-filled from this record. Changes are validated server-side on submit.'
                            : 'The form below is validated server-side on submit. Creating a record refreshes the table.'}
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="space-y-5 py-2" aria-busy="true">
                        <div className="space-y-2">
                            <Skeleton className="h-3.5 w-20" />
                            <Skeleton className="h-9 w-full" />
                        </div>
                        <div className="space-y-2">
                            <Skeleton className="h-3.5 w-24" />
                            <Skeleton className="h-9 w-full" />
                        </div>
                    </div>
                ) : null}

                {loadError && !loading ? (
                    <div
                        role="alert"
                        className="flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"
                    >
                        Could not load the form. Please try again.
                        <Button type="button" variant="outline" size="sm" onClick={() => setRetry((value) => value + 1)}>
                            Retry
                        </Button>
                    </div>
                ) : null}

                {doc !== null && contractMismatch ? (
                    <div
                        role="alert"
                        className="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"
                    >
                        Unsupported contract version <code>{doc.contract}</code> — expected <code>{CONTRACT_VERSION}</code>.
                    </div>
                ) : null}

                {doc !== null && !contractMismatch ? (
                    <SchemaRenderer
                        key={session}
                        schema={doc.schema}
                        data={recordData ?? doc.data}
                        errors={doc.errors}
                        schemaId={doc.id}
                        submitLabel={action.label}
                        submitUrl={isEdit
                            ? submitUrl ?? `/refilament/table/${tableId}/action/${action.name}`
                            : submitUrl}
                        submitRecord={isEdit ? recordId : undefined}
                        onSuccess={handleSucceeded}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
