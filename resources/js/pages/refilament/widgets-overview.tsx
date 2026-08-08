import { Link } from '@inertiajs/react';

import AppShell from '@/components/shell/AppShell';
import StatsOverviewWidget from '@/widgets/StatsOverviewWidget';
import type { RefilamentWidget } from '@/widgets/types';

interface WidgetsOverviewProps {
    /** The serialized `stats_overview` widget node (slice 3.1). */
    widget: RefilamentWidget;
}

/**
 * A free-standing demo page hosting a StatsOverviewWidget (slice 3.1). The
 * page receives one serialized widget node as an Inertia prop and hands it to
 * the widget renderer, which draws the stat-card grid with no round trips.
 */
export default function WidgetsOverview(props: WidgetsOverviewProps) {
    return (
        <AppShell>
            <main className="mx-auto w-full max-w-5xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Widgets</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        A stats overview widget, served as a{' '}
                        <code>stats_overview</code> node from <code>/refilament/widgets</code> — a
                        static server snapshot rendered without any polling or round trips.
                    </p>
                </header>

                <StatsOverviewWidget node={props.widget} />

                <footer className="mt-8 text-center text-sm">
                    <Link
                        href="/refilament/playground"
                        className="text-indigo-600 transition hover:text-indigo-800 hover:underline"
                    >
                        Back to the playground
                    </Link>
                </footer>
            </main>
        </AppShell>
    );
}