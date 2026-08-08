import AppShell from '@/components/shell/AppShell';
import StatsOverviewWidget from '@/widgets/StatsOverviewWidget';
import type { RefilamentWidget } from '@/widgets/types';

interface DashboardProps {
    /** The panel's registered widgets, serialized to snapshot nodes per request. */
    widgets: RefilamentWidget[];
}

/**
 * The panel dashboard (slice 1.9) served at /refilament — renders each widget
 * node the server serialized. Widgets are static snapshots; nothing here
 * polls or refetches. Unknown widget types are skipped so a newly-added
 * widget never breaks the shell.
 */
export default function Dashboard(props: DashboardProps) {
    return (
        <AppShell>
            <main className="mx-auto w-full max-w-5xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Dashboard</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        The panel home — your registered widgets, served as static snapshots
                        from <code>/refilament</code>.
                    </p>
                </header>

                {props.widgets.map((widget, index) => (
                    <div key={`${widget.type}-${index}`} className="mb-8 last:mb-0">
                        <WidgetView node={widget} />
                    </div>
                ))}

                {props.widgets.length === 0 ? (
                    <p className="rounded-lg border border-border bg-muted/40 p-6 text-sm text-muted-foreground">
                        No widgets are registered on this panel yet.
                    </p>
                ) : null}
            </main>
        </AppShell>
    );
}

function WidgetView({ node }: { node: RefilamentWidget }) {
    switch (node.type) {
        case 'stats_overview':
            return <StatsOverviewWidget node={node} />;
        default:
            return null;
    }
}