import { router } from '@inertiajs/react';

import { Card } from '@/components/ui/card';
import PageActions, { type PageAction } from '@/components/pages/PageActions';
import PageBreadcrumbs, { type PageBreadcrumb } from '@/components/pages/PageBreadcrumbs';
import PageWidgets from '@/components/pages/PageWidgets';
import AppShell from '@/components/shell/AppShell';
import { InfolistRenderer } from '@/infolists/InfolistRenderer';
import type { FieldNode } from '@/schemas/types';
import type { RefilamentWidget } from '@/widgets/types';

interface PageInfolistProps {
    /** The read-only schema — entries carry their server-resolved values. */
    schema: FieldNode[];
    /** The page's heading (the page class's title). */
    infolistTitle: string;
    /** The page's infolist id — payload metadata; no typed endpoint yet. */
    infolistId?: string;
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
 * The generic page behind every page hosting a read-only infolist (the
 * page-infolists slice) — a standalone or custom resource page that declares
 * `infolist()` on its class serves this component with zero consumer React
 * code. Entries resolve their values server-side at payload time (bound to
 * the record on record pages), so this is a pure render: heading,
 * breadcrumbs, header actions (Edit/Delete on a `/{record}/manage` page),
 * widgets, and the read-only entry tree.
 */
export default function PageInfolist(props: PageInfolistProps) {
    return (
        <AppShell>
            <main className="w-full">
                <header className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <PageBreadcrumbs breadcrumbs={props.breadcrumbs ?? []} />
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{props.infolistTitle}</h1>
                        {props.description ? (
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{props.description}</p>
                        ) : null}
                    </div>

                    <PageActions actions={props.pageActions ?? []} onSucceeded={() => router.reload()} />
                </header>

                <PageWidgets widgets={props.headerWidgets} columns={props.headerWidgetsColumns} className="mb-6" />

                <Card className="p-6">
                    <InfolistRenderer schema={props.schema} />
                </Card>

                <PageWidgets widgets={props.footerWidgets} columns={props.footerWidgetsColumns} className="mt-6" />

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    {props.infolistId ? `infolist ${props.infolistId} · ` : ''}read-only record display
                </footer>
            </main>
        </AppShell>
    );
}
