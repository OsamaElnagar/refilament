import { router } from '@inertiajs/react';

import PageActions, { type PageAction } from '@/components/pages/PageActions';
import PageBreadcrumbs, { type PageBreadcrumb } from '@/components/pages/PageBreadcrumbs';
import PageWidgets from '@/components/pages/PageWidgets';
import AppShell from '@/components/shell/AppShell';
import { panelUrl } from '@/lib/panel';
import TableRenderer from '@/tables/TableRenderer';
import type { TablePayload } from '@/tables/types';
import type { RefilamentWidget } from '@/widgets/types';

/** The page-level extras every resource page payload can carry (slice 1.10). */
interface ResourceTableProps extends TablePayload {
    /** Page breadcrumbs (slice 1.11) — omitted when the panel toggle is off. */
    breadcrumbs?: PageBreadcrumb[];
    /** Page header actions — omitted when the page declares none. */
    pageActions?: PageAction[];
    /** Widgets rendered above the table (slice 1.10) — omitted when none. */
    headerWidgets?: RefilamentWidget[];
    headerWidgetsColumns?: number;
    /** Widgets rendered below the table — omitted when none. */
    footerWidgets?: RefilamentWidget[];
    footerWidgetsColumns?: number;
}

/**
 * The generic page behind every auto-registered resource route — the package
 * serves GET /refilament/{tableId} for each discovered resource, so no
 * per-resource page component is needed. The title comes from the table's
 * `heading()` when set, else a capitalized version of the table id. The page
 * header hosts the page-level actions (the default CreateAction, which
 * navigates to the create page) and any header/footer widgets.
 */
function titleFor(props: TablePayload): string {
    if (props.heading !== undefined) {
        return props.heading;
    }

    if (props.id === undefined) {
        return 'Records';
    }

    return props.id.charAt(0).toUpperCase() + props.id.slice(1);
}

export default function ResourceTable(props: ResourceTableProps) {
    const title = titleFor(props);

    return (
        <AppShell>
            <main className="w-full">
                <header className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <PageBreadcrumbs breadcrumbs={props.breadcrumbs ?? []} />
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            An auto-registered page — the package serves{' '}
                            <code>{panelUrl(`/${props.id}`)}</code> for every discovered resource, no
                            app-side route or page component needed.
                        </p>
                    </div>

                    <PageActions actions={props.pageActions ?? []} onSucceeded={() => router.reload()} />
                </header>

                <PageWidgets widgets={props.headerWidgets} columns={props.headerWidgetsColumns} className="mb-6" />

                <TableRenderer initial={props} />

                <PageWidgets widgets={props.footerWidgets} columns={props.footerWidgetsColumns} className="mt-6" />

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    {props.total} records · auto-registered page
                </footer>
            </main>
        </AppShell>
    );
}
