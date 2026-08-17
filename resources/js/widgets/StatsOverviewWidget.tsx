import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Icon } from '@/components/icon';
import { cn } from '@/lib/utils';
import type { StatsOverviewWidgetNode, WidgetStat } from '@/widgets/types';

/** Tailwind text classes per color, over the default foreground. */
const STAT_COLORS: Record<string, string> = {
    primary: 'text-primary',
    secondary: 'text-muted-foreground',
    danger: 'text-rose-600 dark:text-rose-400',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-600 dark:text-sky-400',
};

/**
 * Renders a `stats_overview` widget (slice 3.1) as a grid of stat cards.
 * The widget is a static server snapshot — nothing here polls or refetches;
 * it renders exactly the `stats` it received. The card grid's `columns`
 * defaults to 2 (matching the PHP default) when the node omits it.
 */
export default function StatsOverviewWidget({ node }: { node: StatsOverviewWidgetNode }) {
    const columns = node.columns ?? 2;

    return (
        <section className="w-full">
            {node.heading ? (
                <div className="mb-4">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">{node.heading}</h2>
                    {node.description ? (
                        <p className="mt-1 text-sm text-muted-foreground">{node.description}</p>
                    ) : null}
                </div>
            ) : null}

            <div
                className={cn('grid gap-4', {
                    'grid-cols-1': columns === 1,
                    'grid-cols-1 sm:grid-cols-2': columns === 2,
                    'grid-cols-1 sm:grid-cols-3': columns === 3,
                    'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4': columns >= 4,
                })}
            >
                {node.stats.map((stat, index) => (
                    <StatCard key={`${stat.label}-${index}`} stat={stat} />
                ))}
            </div>
        </section>
    );
}

function StatCard({ stat }: { stat: WidgetStat }) {
    return (
        <Card className="p-5">
            <CardHeader className="gap-0 px-0">
                <div className="flex items-center justify-between gap-3">
                    <CardTitle className="text-sm font-medium text-muted-foreground">{stat.label}</CardTitle>
                    {stat.icon ? <Icon name={stat.icon} className="h-4 w-4 text-muted-foreground" /> : null}
                </div>
            </CardHeader>
            <CardContent className="px-0">
                <p className={cn('text-3xl font-semibold tracking-tight', stat.color ? STAT_COLORS[stat.color] : 'text-foreground')}>
                    {String(stat.value)}
                </p>
                {stat.description ? (
                    <CardDescription className="mt-1">{stat.description}</CardDescription>
                ) : null}
            </CardContent>
        </Card>
    );
}