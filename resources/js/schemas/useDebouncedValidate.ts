import { useEffect, useMemo, useRef, useState } from 'react';

import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import type { FieldNode } from '@/schemas/types';

const DEBOUNCE_MS = 500;

export interface DebouncedValidateOptions {
    /** The record being edited — its unique rule is ignored (mirrors record update). */
    record?: string | number;
}

export interface DebouncedValidateState {
    /** Server errors for live-validated fields, keyed by field name. */
    errors: Record<string, string[]>;
    /** True while a live check is in flight for the field (for a spinner). */
    checking: Record<string, boolean>;
}

export function validateUrl(schemaId: string): string {
    return panelUrl(`/schema/${encodeURIComponent(schemaId)}/validate`);
}

/**
 * Live, debounced server validation for fields that carry a `unique` rule
 * (slice 2.5 — docs/CONTRACT.md, "Live validation"). This is the deliberate,
 * visible server round-trip behind "is this slug/email already taken" checks
 * — not a hidden Livewire closure re-execution. The rules stay
 * server-authoritative: the client only sends the field's value and maps the
 * returned error back onto the field.
 *
 * A field is validated while its value settles after a debounce. Requests
 * are superseded: a newer value change aborts the in-flight check for the
 * same field, and stale responses are dropped.
 */
export function useDebouncedValidate(
    schemaId: string | undefined,
    schema: FieldNode[],
    values: Record<string, unknown>,
    options: DebouncedValidateOptions = {},
): DebouncedValidateState {
    const candidates = useMemo(
        () => schema.filter((node) => node.name && isUniqueInRules(node)),
        [schema],
    );

    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [checking, setChecking] = useState<Record<string, boolean>>({});

    const valuesRef = useRef(values);
    valuesRef.current = values;

    const lastSignature = useRef<Record<string, string>>({});
    const timers = useRef<Record<string, number>>({});
    const aborters = useRef<Record<string, AbortController>>({});

    useEffect(() => {
        for (const node of candidates) {
            const signature = String(valuesRef.current[node.name] ?? '');

            if (lastSignature.current[node.name] === signature) {
                continue;
            }

            lastSignature.current[node.name] = signature;

            window.clearTimeout(timers.current[node.name]);

            // An empty value is trivially unique — no round trip, no stale error.
            if (signature === '') {
                setErrors((current) => {
                    if (!(node.name in current)) {
                        return current;
                    }
                    const next = { ...current };
                    delete next[node.name];
                    return next;
                });
                continue;
            }

            timers.current[node.name] = window.setTimeout(() => {
                check(node.name, signature);
            }, DEBOUNCE_MS);
        }
    }, [candidates, values]);

    const check = (field: string, signature: string): void => {
        aborters.current[field]?.abort();

        const controller = new AbortController();
        aborters.current[field] = controller;

        setChecking((current) => ({ ...current, [field]: true }));

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };

        const csrfToken = readCsrfToken();

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        fetch(validateUrl(schemaId ?? 'default'), {
            method: 'POST',
            headers,
            body: JSON.stringify({
                field,
                value: valuesRef.current[field] ?? '',
                data: valuesRef.current,
                ...(options.record !== undefined ? { record: String(options.record) } : {}),
            }),
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    // Non-422 failures (unknown schema/field, no unique rule) — a
                    // configuration problem. Drop the check silently rather than
                    // surface a spurious error on the field.
                    throw new Error(`validate returned ${response.status}`);
                }

                return response.json() as Promise<{ valid?: boolean; errors?: Record<string, string[]> }>;
            })
            .then((payload) => {
                if (lastSignature.current[field] !== signature) {
                    return;
                }

                setErrors((current) => {
                    const next = { ...current };
                    const fieldErrors = payload.errors?.[field] ?? [];

                    if (fieldErrors.length > 0) {
                        next[field] = fieldErrors;
                    } else {
                        delete next[field];
                    }

                    return next;
                });
            })
            .catch(() => {
                // Aborted or unreachable — keep whatever was last shown.
            })
            .finally(() => {
                if (lastSignature.current[field] === signature) {
                    setChecking((current) => ({ ...current, [field]: false }));
                }
            });
    };

    useEffect(() => {
        const timersSnapshot = timers.current;
        const abortersSnapshot = aborters.current;

        return () => {
            for (const timer of Object.values(timersSnapshot)) {
                window.clearTimeout(timer);
            }

            for (const aborter of Object.values(abortersSnapshot)) {
                aborter.abort();
            }
        };
    }, []);

    return { errors, checking };
}

function isUniqueInRules(node: FieldNode): boolean {
    return (node.validation ?? []).some((rule) => rule.startsWith('unique:'));
}