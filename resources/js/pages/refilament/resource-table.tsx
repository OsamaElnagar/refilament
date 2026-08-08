import AppShell from '@/components/shell/AppShell';
import { panelUrl } from '@/lib/panel';
import TableRenderer from '@/tables/TableRenderer';
import type { TablePayload } from '@/tables/types';

/**
 * The generic page behind every auto-registered resource route — the package
 * serves GET /refilament/{tableId} for each discovered resource, so no
 * per-resource page component is needed. The title comes from the table's
 * `heading()` when set, else a capitalized version of the table id.
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

export default function ResourceTable(props: TablePayload) {
    const title = titleFor(props);

    return (
        <AppShell>
            <main className="mx-auto w-full max-w-4xl">
                <header className="mb-8 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            An auto-registered page — the package serves{' '}
                            <code>{panelUrl(`/${props.id}`)}</code> for every discovered resource, no
                            app-side route or page component needed. Create happens in a
                            modal (slice 1.1) — the full-page create route remains at{' '}
                            <code>{panelUrl(`/${props.id}/create`)}</code>.
                        </p>
                    </div>
                </header>

                <TableRenderer initial={props} />

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    {props.total} records · auto-registered page
                </footer>
            </main>
        </AppShell>
    );
}
