import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { Card } from '@/components/ui/card';
import PageActions, { type PageAction } from '@/components/pages/PageActions';
import PageBreadcrumbs, { type PageBreadcrumb } from '@/components/pages/PageBreadcrumbs';
import PageWidgets from '@/components/pages/PageWidgets';
import AppShell from '@/components/shell/AppShell';
import SchemaRenderer from '@/schemas/SchemaRenderer';
import { CONTRACT_VERSION } from '@/schemas/types';
import type { SchemaDocument } from '@/schemas/types';
import type { RefilamentWidget } from '@/widgets/types';

interface PageFormProps extends SchemaDocument {
    /** The page's heading (the page class's title). */
    formTitle: string;
    /** The submit button label (the page's getFormSubmitLabel()). */
    formSubmitLabel?: string;
    /**
     * When true the browser prompts before navigating away with a dirty
     * form (the page's `$hasUnsavedDataChangesAlert`). The guard intercepts
     * Inertia's `before` event and asks for confirmation — on confirm it
     * re-issues the same visit.
     */
    hasUnsavedDataChangesAlert?: boolean;
    /**
     * The record-scoped submit endpoint (the record-pages slice) — a
     * record-scoped form page (`/{record}/manage`) serializes this so the
     * save validates + updates the URL record server-side instead of posting
     * to the generic typed submit endpoint. When set, `record` holds the
     * record and it lives in the URL (never duplicated in the body).
     */
    submitUrl?: string;
    /** The record the form edits — shipped by record-scoped form pages. */
    record?: string | number;
    /** Resource-page props — omitted on standalone pages. */
    breadcrumbs?: PageBreadcrumb[];
    pageActions?: PageAction[];
    headerWidgets?: RefilamentWidget[];
    headerWidgetsColumns?: number;
    footerWidgets?: RefilamentWidget[];
    footerWidgetsColumns?: number;
    /** Optional page description rendered under the heading. */
    description?: string;
}

/**
 * The generic page behind every page hosting a form (the page-forms slice) —
 * a standalone or custom resource page that declares `form()` on its class
 * serves this component with zero consumer React code. State is client-held
 * (SchemaRenderer), validated and persisted server-side through the typed
 * submit endpoint; a successful save re-requests the page so server-derived
 * values refresh.
 *
 * The unsaved-changes guard (mirroring Filament's HasUnsavedDataChangesAlert)
 * listens to Inertia's `before` event. A dirty form cancels the visit and,
 * on confirmation, re-issues it with the same options — the re-issued visit
 * passes because the dirty flag clears first. The dirty flag lives in a ref
 * so the guard never reads a stale closure, and the form remounts after each
 * save (keyed by version) so a reload lands on fresh server values.
 */
export default function PageForm(props: PageFormProps) {
    const dirtyRef = useRef(false);
    const [savedVersion, setSavedVersion] = useState(0);

    useEffect(() => {
        if (!props.hasUnsavedDataChangesAlert) {
            return;
        }

        const unlisten = router.on('before', (event) => {
            if (!dirtyRef.current) {
                return;
            }

            event.preventDefault();

            if (!window.confirm('You have unsaved changes. Are you sure you want to leave?')) {
                return;
            }

            // The user confirmed leaving — clear the guard and re-issue the
            // exact visit that was cancelled, so navigation proceeds.
            dirtyRef.current = false;

            const visit = event.detail.visit;

            router.visit(visit.url, { ...visit });
        });

        return unlisten;
    }, [props.hasUnsavedDataChangesAlert]);

    if (props.contract !== CONTRACT_VERSION) {
        return (
            <AppShell>
                <main className="w-full">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Unsupported contract version <code>{props.contract}</code> — expected{' '}
                        <code>{CONTRACT_VERSION}</code>.
                    </div>
                </main>
            </AppShell>
        );
    }

    const handleSaved = (): void => {
        dirtyRef.current = false;

        // Reload for fresh server-derived values, and remount the form only
        // AFTER the reload lands — remounting in the same commit as the
        // success state would discard SchemaRenderer's success effect (the
        // toast never fires). The reload's navigation round-trip gives the
        // toast time to render; the Toaster lives outside the page, so it
        // survives the swap.
        router.reload({
            onSuccess: () => setSavedVersion((version) => version + 1),
        });
    };

    return (
        <AppShell>
            <main className="w-full">
                <header className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <PageBreadcrumbs breadcrumbs={props.breadcrumbs ?? []} />
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{props.formTitle}</h1>
                        {props.description ? (
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{props.description}</p>
                        ) : null}
                    </div>

                    <PageActions actions={props.pageActions ?? []} onSucceeded={() => router.reload()} />
                </header>

                <PageWidgets widgets={props.headerWidgets} columns={props.headerWidgetsColumns} className="mb-6" />

                <Card className="p-6">
                    <SchemaRenderer
                        key={savedVersion}
                        schema={props.schema}
                        data={props.data}
                        errors={props.errors}
                        schemaId={props.id}
                        submitLabel={props.formSubmitLabel ?? 'Save'}
                        submitUrl={props.submitUrl}
                        submitRecord={props.record}
                        submitRecordInUrl={props.submitUrl ? true : undefined}
                        operation={props.submitUrl ? 'edit' : undefined}
                        onValuesChange={() => {
                            // The guard reads the ref synchronously, so a
                            // change marks the form dirty before any visit
                            // can be intercepted.
                            dirtyRef.current = true;
                        }}
                        onSuccess={handleSaved}
                    />
                </Card>

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    contract v{props.contract} · page form
                </footer>
            </main>
        </AppShell>
    );
}
