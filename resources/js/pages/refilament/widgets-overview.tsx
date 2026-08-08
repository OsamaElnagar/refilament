import { Link } from '@inertiajs/react';

import AppShell from '@/components/shell/AppShell';
import { panelUrl } from '@/lib/panel';
import { WidgetRenderer } from '@/widgets/WidgetRenderer';
import type { RefilamentWidget } from '@/widgets/types';

interface WidgetsOverviewProps {
    /** Serialized widget nodes (slices 3.1/3.2), one per dashboard tile. */
    widgets: RefilamentWidget[];
}

/**
 * A free-standing demo page hosting several widgets (slices 3.1/3.2). The
 * page receives serialized widget nodes as an Inertia prop and hands each to
 * the widget renderer, which draws it with no round trips. This mirrors the
 * list the (not-yet-built) panel dashboard shell will aggregate.
 */
export default function WidgetsOverview(props: WidgetsOverviewProps) {
    return (
        <AppShell>
            <main className="mx-auto w-full max-w-5xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Widgets</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Widgets served as static JSON nodes from{' '}
                        <code>{panelUrl('/widgets')}</code> — a stats overview and a chart —
                        rendered without any polling or round trips.
                    </p>
                </header>

                <div className="space-y-6">
                    {props.widgets.map((widget, index) => (
                        <WidgetRenderer key={`${widget.type}-${index}`} node={widget} />
                    ))}
                </div>

                <footer className="mt-8 text-center text-sm">
                    <Link
                        href={panelUrl('/playground')}
                        className="text-indigo-600 transition hover:text-indigo-800 hover:underline"
                    >
                        Back to the playground
                    </Link>
                </footer>
            </main>
        </AppShell>
    );
}