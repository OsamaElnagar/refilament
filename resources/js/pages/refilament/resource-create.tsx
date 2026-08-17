import { router } from '@inertiajs/react';

import { Card } from '@/components/ui/card';
import PageActions, { type PageAction } from '@/components/pages/PageActions';
import PageBreadcrumbs, { type PageBreadcrumb } from '@/components/pages/PageBreadcrumbs';
import PageWidgets from '@/components/pages/PageWidgets';
import AppShell from '@/components/shell/AppShell';
import SchemaRenderer from '@/schemas/SchemaRenderer';
import { CONTRACT_VERSION } from '@/schemas/types';
import type { SchemaDocument } from '@/schemas/types';
import { panelUrl } from '@/lib/panel';
import type { RefilamentWidget } from '@/widgets/types';

interface ResourceCreateProps extends SchemaDocument {
    /** The resource's table id — the list route to return to on success. */
    resource: string;
    /** Display title derived from the resource's model (e.g. "User"). */
    resourceTitle: string;
    /** Page breadcrumbs (slice 1.11) — omitted when the panel toggle is off. */
    breadcrumbs?: PageBreadcrumb[];
    /** Page header actions (slice 1.10) — omitted when the page declares none. */
    pageActions?: PageAction[];
    /** Widgets rendered above the form (slice 1.10) — omitted when none. */
    headerWidgets?: RefilamentWidget[];
    headerWidgetsColumns?: number;
    footerWidgets?: RefilamentWidget[];
    footerWidgetsColumns?: number;
}

/**
 * The generic page behind every auto-registered create route — the package
 * serves GET /refilament/{resource}/create for each discovered resource, so
 * no per-resource create page component is needed.
 */
export default function ResourceCreate(props: ResourceCreateProps) {
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

    return (
        <AppShell>
            <main className="w-full">
                <header className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <PageBreadcrumbs breadcrumbs={props.breadcrumbs ?? []} />
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Create {props.resourceTitle}
                        </h1>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            An auto-registered create page — the package serves{' '}
                            <code>{panelUrl(`/${props.resource}/create`)}</code> for every discovered
                            resource, no app-side route or page component needed.
                        </p>
                    </div>

                    <PageActions actions={props.pageActions ?? []} onSucceeded={() => router.reload()} />
                </header>

                <PageWidgets widgets={props.headerWidgets} columns={props.headerWidgetsColumns} className="mb-6" />

                <Card className="p-6">
                    <SchemaRenderer
                        schema={props.schema}
                        data={props.data}
                        errors={props.errors}
                        schemaId={props.id}
                        submitLabel={`Create ${props.resourceTitle}`}
                        operation="create"
                        onSuccess={() => router.visit(panelUrl(`/${props.resource}`))}
                    />
                </Card>

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    contract v{props.contract} · auto-registered create page
                </footer>
            </main>
        </AppShell>
    );
}
