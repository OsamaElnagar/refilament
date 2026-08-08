import { useEffect, useMemo, useRef, useState } from 'react';

import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import type { FieldNode, SelectOption } from '@/schemas/types';

const DEBOUNCE_MS = 300;

const RESOLVE_OPTIONS_URL = panelUrl('/schema/resolve-options');

export interface DependentOptionsState {
    optionsByField: Record<string, SelectOption[]>;
    loadingFields: Record<string, boolean>;
}

function isDependent(node: FieldNode): boolean {
    return (node.dependsOn?.length ?? 0) > 0;
}

/**
 * Watches the dependency values of every field that declares `dependsOn` and
 * fetches fresh options from the typed resolve-options endpoint when a
 * dependency changes. This is the deliberate, visible server round-trip
 * documented in docs/CONTRACT.md ("Options") — not a hidden Livewire-style
 * closure re-execution.
 *
 * Requests are debounced and superseded: a newer dependency change aborts any
 * in-flight fetch for the same field, and stale responses are dropped.
 */
export function useDependentOptions(
    schemaId: string | undefined,
    schema: FieldNode[],
    values: Record<string, unknown>,
): DependentOptionsState {
    const dependents = useMemo(() => schema.filter(isDependent), [schema]);

    const [optionsByField, setOptionsByField] = useState<Record<string, SelectOption[]>>({});
    const [loadingFields, setLoadingFields] = useState<Record<string, boolean>>({});

    const valuesRef = useRef(values);
    valuesRef.current = values;

    const lastSignature = useRef<Record<string, string>>({});
    const timers = useRef<Record<string, number>>({});
    const aborters = useRef<Record<string, AbortController>>({});

    useEffect(() => {
        for (const node of dependents) {
            const signature = node.dependsOn!
                .map((dependency) => String(valuesRef.current[dependency] ?? ''))
                .join('|');

            if (lastSignature.current[node.name] === signature) {
                continue;
            }

            lastSignature.current[node.name] = signature;

            window.clearTimeout(timers.current[node.name]);
            setLoadingFields((current) => ({ ...current, [node.name]: true }));

            timers.current[node.name] = window.setTimeout(() => {
                fetchOptions(node.name, signature);
            }, DEBOUNCE_MS);
        }
    }, [dependents, values]);

    const fetchOptions = (field: string, signature: string): void => {
        aborters.current[field]?.abort();

        const controller = new AbortController();
        aborters.current[field] = controller;

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };

        const csrfToken = readCsrfToken();

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        fetch(RESOLVE_OPTIONS_URL, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                schema: schemaId ?? 'default',
                field,
                data: valuesRef.current,
            }),
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`resolve-options returned ${response.status}`);
                }

                return response.json() as Promise<{ options?: SelectOption[] }>;
            })
            .then((payload) => {
                // A newer dependency change superseded this request — drop it.
                if (lastSignature.current[field] !== signature) {
                    return;
                }

                setOptionsByField((current) => ({
                    ...current,
                    [field]: payload.options ?? [],
                }));
            })
            .catch(() => {
                // Aborted or unreachable — keep the previously resolved options.
            })
            .finally(() => {
                if (lastSignature.current[field] === signature) {
                    setLoadingFields((current) => ({ ...current, [field]: false }));
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

    return { optionsByField, loadingFields };
}
