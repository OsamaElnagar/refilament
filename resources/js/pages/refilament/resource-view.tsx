import { Link } from '@inertiajs/react';

import { Card } from '@/components/ui/card';
import AppShell from '@/components/shell/AppShell';
import { InfolistRenderer } from '@/infolists/InfolistRenderer';
import type { FieldNode } from '@/schemas/types';
import type { TableColumn, TableSummaryMap } from '@/tables/types';

interface ResourceViewProps {
    /** The resource's table id — the list route to return to. */
    resource: string;
    /** Display title derived from the resource's model (e.g. "User"). */
    resourceTitle: string;
    /** The record being viewed. */
    record: string | number;
    /** An infolist schema — rendered when the resource defines an infolist(). */
    schema?: FieldNode[];
    /** The table's column definitions — labels for the read-only list. */
    columns?: TableColumn[];
    /** The record's values, keyed by column name (serialized like a row). */
    values?: Record<string, unknown>;
    /** Dataset-wide footer summaries (slice 1.7), keyed by column name. */
    summary?: TableSummaryMap;
}

/**
 * The generic page behind every auto-registered view route — the package
 * serves GET /refilament/{resource}/{record} (the 'view' slot of the
 * resource's getPages() map) for each discovered resource, so no per-resource
 * view page component is needed. Read-only for now; pairs with the Phase 3
 * infolists once those land. The footer totals (slice 1.7) are computed
 * server-side over the whole dataset — the Ahram report idiom of aggregates
 * beside the read-out.
 */
export default function ResourceView(props: ResourceViewProps) {
    const summaries = Object.entries(props.summary ?? {});

    return (
        <AppShell>
            <main className="mx-auto w-full max-w-3xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        {props.resourceTitle} #{props.record}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        An auto-registered view page — served at{' '}
                        <code>/refilament/{props.resource}/{props.record}</code> from the resource's{' '}
                        <code>getPages()</code> map.
                    </p>
                </header>

                <Card className="p-6">
                    {props.schema && props.schema.length > 0 ? (
                        <InfolistRenderer schema={props.schema} />
                    ) : (
                        <dl className="divide-y divide-border">
                            {(props.columns ?? []).map((column) => (
                                <div
                                    key={column.name}
                                    className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                >
                                    <dt className="w-2/5 shrink-0 text-sm font-medium text-muted-foreground">
                                        {column.label}
                                    </dt>
                                    <dd className="flex-1 break-words text-sm text-foreground">
                                        {props.values?.[column.name] !== undefined &&
                                        props.values?.[column.name] !== null
                                            ? String(props.values?.[column.name])
                                            : '—'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </Card>

                {summaries.length > 0 ? (
                    <Card className="mt-4 p-5">
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            Totals
                        </p>
                        <dl className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {summaries.map(([column, cells]) => (
                                <div key={column} className="rounded-lg bg-muted/40 px-4 py-3">
                                    {cells.map((cell) => (
                                        <div key={cell.label}>
                                            <dt className="text-xs font-medium text-muted-foreground">{cell.label}</dt>
                                            <dd className="mt-1 text-lg font-semibold tracking-tight text-foreground">
                                                {cell.value === null || cell.value === undefined ? '—' : String(cell.value)}
                                            </dd>
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </dl>
                    </Card>
                ) : null}

                <footer className="mt-6 text-center text-sm">
                    <Link
                        href={`/refilament/${props.resource}`}
                        className="text-indigo-600 transition hover:text-indigo-800 hover:underline"
                    >
                        ← Back to the {props.resourceTitle} list
                    </Link>
                </footer>
            </main>
        </AppShell>
    );
}
