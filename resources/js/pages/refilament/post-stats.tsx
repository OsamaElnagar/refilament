import { Link } from '@inertiajs/react';

import { Card } from '@/components/ui/card';
import AppShell from '@/components/shell/AppShell';
import { panelUrl } from '@/lib/panel';

interface PostStatsProps {
    /** The resource's table id — the list route to return to. */
    resource: string;
    /** Display title derived from the resource's model (e.g. "Post"). */
    resourceTitle: string;
    /** Server-computed counts from PostStats::getViewData() (slice 1.6). */
    stats: {
        total: number;
        published: number;
        draft: number;
        archived: number;
    };
}

/**
 * A custom resource page (slice 1.6) — registered in PostResource::getPages()
 * under 'stats' and served at /refilament/posts/stats. Its props are computed
 * server-side by the page class's getViewData() and rendered here; the page
 * system needs no per-resource boilerplate beyond the component.
 */
export default function PostStats(props: PostStatsProps) {
    const cards = [
        { label: 'Total posts', value: props.stats.total, tint: 'text-foreground' },
        { label: 'Published', value: props.stats.published, tint: 'text-emerald-600' },
        { label: 'Draft', value: props.stats.draft, tint: 'text-amber-600' },
        { label: 'Archived', value: props.stats.archived, tint: 'text-zinc-500' },
    ];

    return (
        <AppShell>
            <main className="mx-auto w-full max-w-4xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        {props.resourceTitle} stats
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        A custom page from the resource's <code>getPages()</code> map — served at{' '}
                        <code>{panelUrl(`/${props.resource}/stats`)}</code>, props computed server-side.
                    </p>
                </header>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.label} className="p-5">
                            <p className="text-sm font-medium text-muted-foreground">{card.label}</p>
                            <p className={`mt-1 text-3xl font-semibold tracking-tight ${card.tint}`}>
                                {card.value}
                            </p>
                        </Card>
                    ))}
                </div>

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
