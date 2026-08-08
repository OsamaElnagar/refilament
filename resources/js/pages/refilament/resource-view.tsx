import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { Card } from '@/components/ui/card';
import AppShell from '@/components/shell/AppShell';
import { InfolistRenderer } from '@/infolists/InfolistRenderer';
import type { FieldNode } from '@/schemas/types';
import type { TablePayload } from '@/tables/types';
import TableRenderer, { type TableSource } from '@/tables/TableRenderer';
import type { TableColumn, TableSummaryMap } from '@/tables/types';
import { panelUrl } from '@/lib/panel';

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
    /** Relation managers the resource registered (slice 1.8) — may be empty. */
    relations?: RelationTab[];
}

/** A relation manager the resource registered — rendered as a tab on the page. */
interface RelationTab {
    /** The to-many relationship name, the {relation} URL segment. */
    name: string;
    /** Display title for the tab. */
    label: string;
}

/**
 * An embedded, relation-scoped table payload. The relation index endpoint is
 * fetched on mount by TableRenderer (its `source` override forces the first
 * fetch), so this only needs a valid, empty shape to render the shell.
 */
function emptyRelationPayload(name: string): TablePayload {
    return {
        id: name,
        columns: [],
        recordsPerPage: 10,
        recordsPerPageSelectOptions: [5, 10, 25],
        page: 1,
        perPage: 10,
        total: 0,
        lastPage: 1,
        rows: [],
    };
}

/**
 * The generic page behind every auto-registered view route — the package
 * serves GET /refilament/{resource}/{record} (the 'view' slot of the
 * resource's getPages() map) for each discovered resource, so no per-resource
 * view page component is needed. Read-only for now; pairs with the Phase 3
 * infolists once those land. The footer totals (slice 1.7) are computed
 * server-side over the whole dataset — the Ahram report idiom of aggregates
 * beside the read-out.
 *
 * When the resource registers relation managers, a tab bar appears (slice
 * 1.8): a "Details" tab hosts the record's read-out, and one tab per relation
 * hosts the manager's table scoped to this record, served through the
 * relation endpoints. Only the active tab's table is mounted, so only its
 * index is fetched.
 */
export default function ResourceView(props: ResourceViewProps) {
    const summaries = Object.entries(props.summary ?? {});
    const relations = props.relations ?? [];
    const hasRelations = relations.length > 0;

    // Tabs are purely client-side — no URL segment, no server state.
    const [activeTab, setActiveTab] = useState<string>('details');

    return (
        <AppShell>
            <main className="mx-auto w-full max-w-3xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        {props.resourceTitle} #{props.record}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        An auto-registered view page — served at{' '}
                        <code>{panelUrl(`/${props.resource}/${props.record}`)}</code> from the resource's{' '}
                        <code>getPages()</code> map.
                    </p>
                </header>

                {hasRelations ? (
                    <nav
                        aria-label="View page sections"
                        className="mb-6 flex flex-wrap items-center gap-2 border-b border-border pb-3"
                    >
                        <TabButton active={activeTab === 'details'} onClick={() => setActiveTab('details')}>
                            Details
                        </TabButton>
                        {relations.map((relation) => (
                            <TabButton
                                key={relation.name}
                                active={activeTab === relation.name}
                                onClick={() => setActiveTab(relation.name)}
                            >
                                {relation.label}
                            </TabButton>
                        ))}
                    </nav>
                ) : null}

                {activeTab === 'details' ? (
                    <>
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
                    </>
                ) : null}

                {relations.map((relation) =>
                    activeTab === relation.name ? <RelationTabPanel key={relation.name} props={props} relation={relation} /> : null,
                )}

                <footer className="mt-6 text-center text-sm">
                    <Link
                        href={panelUrl(`/${props.resource}`)}
                        className="text-indigo-600 transition hover:text-indigo-800 hover:underline"
                    >
                        ← Back to the {props.resourceTitle} list
                    </Link>
                </footer>
            </main>
        </AppShell>
    );
}

function TabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium transition ${
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

function RelationTabPanel({
    props,
    relation,
}: {
    props: ResourceViewProps;
    relation: RelationTab;
}) {
    // Scoped relation endpoints. The index is fetched on mount; actions and
    // record pre-fills route through the manager's own endpoint so every
    // change stays attached to this owner.
    const source = useMemo<TableSource>(
        () => ({
            index: panelUrl(`/relation/${props.resource}/${String(props.record)}/${relation.name}`),
            action: (_recordId, actionName) =>
                panelUrl(`/relation/${props.resource}/${String(props.record)}/${relation.name}/action/${actionName}`),
            record: (recordId) =>
                panelUrl(`/relation/${props.resource}/${String(props.record)}/${relation.name}/record/${String(recordId)}`),
        }),
        [props.resource, props.record, relation.name],
    );

    return <TableRenderer initial={emptyRelationPayload(relation.name)} source={source} />;
}