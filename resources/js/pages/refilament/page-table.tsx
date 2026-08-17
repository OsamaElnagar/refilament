import { router } from '@inertiajs/react';

import PageActions, { type PageAction } from '@/components/pages/PageActions';
import PageBreadcrumbs, { type PageBreadcrumb } from '@/components/pages/PageBreadcrumbs';
import PageWidgets from '@/components/pages/PageWidgets';
import AppShell from '@/components/shell/AppShell';
import TableRenderer from '@/tables/TableRenderer';
import type { TablePayload } from '@/tables/types';
import type { RefilamentWidget } from '@/widgets/types';

interface PageTableProps extends TablePayload {
    /** The page's own title (the page class's title) — preferred over the table heading/id. */
    tableTitle: string;
    /** Optional page description rendered under the heading. */
    description?: string;
    /** Resource-page props — omitted on standalone pages. */
    breadcrumbs?: PageBreadcrumb[];
    pageActions?: PageAction[];
    headerWidgets?: RefilamentWidget[];
    headerWidgetsColumns?: number;
    footerWidgets?: RefilamentWidget[];
    footerWidgetsColumns?: number;
}

/**
 * The generic page behind every page hosting a table (the pages-as-tables
 * slice) — a standalone or custom resource page that declares `table()` on
 * its class serves this component with zero consumer React code. The payload
 * is exactly what a resource list page ships (definition + first page of
 * rows), so TableRenderer drives the full machinery: server-side pagination,
 * sorting, global search, filters and record/bulk actions through the typed
 * table endpoints — never an Inertia visit per page.
 */
export default function PageTable(props: PageTableProps) {
    const title =
        props.tableTitle ??
        (props.heading !== undefined ? props.heading : props.id !== undefined ? props.id.charAt(0).toUpperCase() + props.id.slice(1) : 'Records');

    return (
        <AppShell>
            <main className="w-full">
                <header className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <PageBreadcrumbs breadcrumbs={props.breadcrumbs ?? []} />
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
                        {props.description ? (
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{props.description}</p>
                        ) : null}
                    </div>

                    <PageActions actions={props.pageActions ?? []} onSucceeded={() => router.reload()} />
                </header>

                <PageWidgets widgets={props.headerWidgets} columns={props.headerWidgetsColumns} className="mb-6" />

                <TableRenderer initial={props} />

                <PageWidgets widgets={props.footerWidgets} columns={props.footerWidgetsColumns} className="mt-6" />

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    {props.total} records · page table
                </footer>
            </main>
        </AppShell>
    );
}
