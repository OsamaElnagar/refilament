import ChartWidget from '@/widgets/ChartWidget';
import StatsOverviewWidget from '@/widgets/StatsOverviewWidget';
import TableWidget from '@/widgets/TableWidget';
import type { RefilamentWidget } from '@/widgets/types';

/**
 * Renders any serialized widget node by its `type` (docs/CONTRACT.md,
 * "Widgets"). The registry is a small switch over the built-in widget types —
 * the same additive pattern as the field/layout/entry registries. New widget
 * types (a plugin-contributed dashboard tile, say) add a branch here and a
 * renderer alongside it.
 */
export function WidgetRenderer({ node }: { node: RefilamentWidget }) {
    switch (node.type) {
        case 'stats_overview':
            return <StatsOverviewWidget node={node} />;
        case 'chart_bar':
        case 'chart_line':
        case 'chart_pie':
        case 'chart_doughnut':
            return <ChartWidget node={node} />;
        case 'table':
            return <TableWidget node={node} />;
        default:
            return <p className="text-sm text-muted-foreground">Unknown widget type.</p>;
    }
}