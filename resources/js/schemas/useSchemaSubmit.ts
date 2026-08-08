import { useCallback, useRef, useState } from 'react';

import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import type { NotificationPayload } from '@/notifications/renderNotification';

function submitUrl(schemaId: string): string {
    return panelUrl(`/schema/${encodeURIComponent(schemaId)}/submit`);
}

export interface SchemaSubmitOptions {
    /**
     * Override the typed submit endpoint. The edit modal (slice 1.2)
     * submits through the table action endpoint instead, so the record and
     * its validated data stay in one request. The full-page edit (slice
     * 1.7) submits through the typed record update endpoint, where the
     * record lives in the URL.
     */
    endpoint?: string;
    /**
     * The record being edited — included in the body as `record` unless
     * `recordInUrl` is set, so the action endpoint resolves and validates
     * against it.
     */
    record?: string | number;
    /**
     * The record lives in the endpoint URL (the typed record update
     * endpoint, slice 1.7) — do not duplicate it in the body.
     */
    recordInUrl?: boolean;
    /**
     * The form operation ('create' | 'edit'), sent as a query param so the
     * server validates with the matching operation-aware rules (slice C6:
     * a `hiddenOn('create')` field never fails an invisible "required").
     */
    operation?: string;
}

export interface SchemaSubmitState {
    submitting: boolean;
    /** Server validation errors keyed by field name (docs/CONTRACT.md, "Form submission"). */
    errors: Record<string, string[]>;
    /** A generic failure (non-422 response or network error), shown at the form level. */
    submitError: string | null;
    successMessage: string | null;
    /**
     * A rich success notification (slice 3.4), when the schema configured one
     * (`->successNotification()` / `->updateSuccessNotification()`). Takes
     * precedence over `successMessage` for rendering.
     */
    successNotification: NotificationPayload | null;
    /**
     * POST the form data to the typed submit endpoint. Resolves true only on
     * success; on a 422 the returned errors are mapped onto the fields.
     */
    submit: (data: Record<string, unknown>) => Promise<boolean>;
    /** Drop a single field's server error (called as its value changes). */
    clearFieldError: (name: string) => void;
}

/**
 * Submits form data through the typed submit endpoint — never an Inertia
 * visit. The server validates against the schema's rules (the client copy is
 * a hint only) and maps 422 errors back onto the field names.
 *
 * Requests are superseded: only the latest submission's outcome is applied,
 * so a stale response can never clobber a newer one.
 */
export function useSchemaSubmit(schemaId: string | undefined, options: SchemaSubmitOptions = {}): SchemaSubmitState {
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [successNotification, setSuccessNotification] = useState<NotificationPayload | null>(null);

    const submitCount = useRef(0);

    const submit = useCallback(
        async (data: Record<string, unknown>): Promise<boolean> => {
            const requestId = ++submitCount.current;

            setSubmitting(true);
            setSubmitError(null);
            setSuccessMessage(null);
            setSuccessNotification(null);

            const headers: Record<string, string> = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };

            const csrfToken = readCsrfToken();

            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            try {
                const baseUrl = options.endpoint ?? submitUrl(schemaId ?? 'default');
                const url = options.operation ? `${baseUrl}?operation=${encodeURIComponent(options.operation)}` : baseUrl;

                const response = await fetch(url, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify(
                        options.record !== undefined && !options.recordInUrl
                            ? { record: String(options.record), data }
                            : { data },
                    ),
                });

                if (requestId !== submitCount.current) {
                    return false;
                }

                if (!response.ok) {
                    const payload = (await response.json().catch(() => null)) as {
                        errors?: Record<string, string[]>;
                        message?: string;
                    } | null;

                    if (response.status === 422) {
                        setErrors(payload?.errors ?? {});
                    } else {
                        // 404/500/network — no field errors, but the user
                        // must not be left with a silent failure (or a stale
                        // error from a prior attempt).
                        setSubmitError(payload?.message ?? 'Something went wrong. Please try again.');
                    }

                    return false;
                }

                const payload = (await response.json()) as { message?: string; notification?: NotificationPayload };

                setErrors({});
                setSuccessMessage(payload.message ?? null);
                setSuccessNotification(payload.notification ?? null);

                return true;
            } catch {
                setSubmitError('Network error — please check your connection and try again.');

                return false;
            } finally {
                if (requestId === submitCount.current) {
                    setSubmitting(false);
                }
            }
        },
        [schemaId, options.endpoint, options.record, options.recordInUrl, options.operation],
    );

    const clearFieldError = useCallback((name: string): void => {
        setErrors((current) => {
            if (!(name in current)) {
                return current;
            }

            const next = { ...current };
            delete next[name];

            return next;
        });
    }, []);

    return { submitting, errors, submitError, successMessage, successNotification, submit, clearFieldError };
}
