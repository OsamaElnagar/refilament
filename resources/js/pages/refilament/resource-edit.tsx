import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { Card } from '@/components/ui/card';
import AppShell from '@/components/shell/AppShell';
import SchemaRenderer from '@/schemas/SchemaRenderer';
import { CONTRACT_VERSION } from '@/schemas/types';
import type { SchemaDocument } from '@/schemas/types';
import type { TablePayload } from '@/tables/types';
import TableRenderer, { type TableSource } from '@/tables/TableRenderer';

/** A relation manager the resource registered — rendered as a tab under the form. */
interface RelationTab {
    /** The to-many relationship name, the {relation} URL segment. */
    name: string;
    /** Display title for the tab. */
    label: string;
}

interface ResourceEditProps extends SchemaDocument {
    /** The resource's table id — the list route to return to on success. */
    resource: string;
    /** Display title derived from the resource's model (e.g. "User"). */
    resourceTitle: string;
    /** The record being edited. */
    record: string | number;
    /** Relation managers the resource registered (slice 1.8) — may be empty. */
    relations?: RelationTab[];
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
 * The generic page behind every auto-registered edit route — the package
 * serves GET /refilament/{resource}/{record}/edit for each discovered
 * resource (the 'edit' slot of the resource's getPages() map), so no
 * per-resource edit page component is needed. The form is pre-filled from
 * the record server-side and submits through the typed record update
 * endpoint (slice 1.7) — validated against the form's rules with the unique
 * rule ignoring the record, and the record's fresh values returned — so no
 * table edit action is required for the page to save.
 *
 * When the resource registers relation managers, a tab bar appears above the
 * form (slice 1.8): the "Form" tab hosts the edit schema, and one tab per
 * relation hosts the manager's table scoped to this record, served through
 * the relation endpoints. Only the active tab's table is mounted, so only its
 * index is fetched.
 */
export default function ResourceEdit(props: ResourceEditProps) {
    const relations = props.relations ?? [];
    const hasRelations = relations.length > 0;

    // Tabs are purely client-side — no URL segment, no server state (the
    // address bar stays the edit page's own, per docs/ARCHITECTURE.md).
    const [activeTab, setActiveTab] = useState<string>('form');

    if (props.contract !== CONTRACT_VERSION) {
        return (
            <AppShell>
                <main className="mx-auto w-full max-w-2xl">
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
            <main className="mx-auto w-full max-w-2xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        Edit {props.resourceTitle} #{props.record}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        An auto-registered edit page — served at{' '}
                        <code>/refilament/{props.resource}/{props.record}/edit</code> from the
                        resource's <code>getPages()</code> map, pre-filled from the record and saved
                        through the typed update endpoint.
                    </p>
                </header>

                {hasRelations ? (
                    <nav
                        aria-label="Edit page sections"
                        className="mb-6 flex flex-wrap items-center gap-2 border-b border-border pb-3"
                    >
                        <TabButton active={activeTab === 'form'} onClick={() => setActiveTab('form')}>
                            Form
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

                {activeTab === 'form' ? (
                    <Card className="p-6">
                        <SchemaRenderer
                            schema={props.schema}
                            data={props.data}
                            errors={props.errors}
                            schemaId={props.id}
                            submitLabel={`Save ${props.resourceTitle}`}
                            submitUrl={`/refilament/table/${props.resource}/record/${props.record}`}
                            submitRecord={props.record}
                            submitRecordInUrl
                            operation="edit"
                            onSuccess={() => router.visit(`/refilament/${props.resource}`)}
                        />
                    </Card>
                ) : null}

                {relations.map((relation) =>
                    activeTab === relation.name ? <RelationTabPanel key={relation.name} props={props} relation={relation} /> : null,
                )}

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    contract v{props.contract} · auto-registered edit page
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
    props: ResourceEditProps;
    relation: RelationTab;
}) {
    // Scoped relation endpoints. The index is fetched on mount; actions and
    // record pre-fills route through the manager's own endpoint so every
    // change stays attached to this owner. `resource`/`record` are stable for
    // the page, so the object identity is stable across renders.
    const source = useMemo<TableSource>(
        () => ({
            index: `/refilament/relation/${props.resource}/${String(props.record)}/${relation.name}`,
            action: (_recordId, actionName) =>
                `/refilament/relation/${props.resource}/${String(props.record)}/${relation.name}/action/${actionName}`,
            record: (recordId) =>
                `/refilament/relation/${props.resource}/${String(props.record)}/${relation.name}/record/${String(recordId)}`,
        }),
        [props.resource, props.record, relation.name],
    );

    return <TableRenderer initial={emptyRelationPayload(relation.name)} source={source} />;
}
