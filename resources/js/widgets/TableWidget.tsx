import TableRenderer from '@/tables/TableRenderer';
import type { TableWidgetNode } from '@/widgets/types';

/**
 * Renders a `table` widget (slice D1) — the Ahram
 * `RecentSalesInvoicesTable` idiom: a widget that is itself a table. The node
 * embeds the table's first page; TableRenderer reuses its typed table
 * endpoint for sorting and pagination, so the widget table must be resolvable
 * by its id server-side (Refilament::registerTable). The snapshot renders
 * with no round trips either way.
 */
export default function TableWidget({ node }: { node: TableWidgetNode }) {
    return (
        <section className="w-full">
            {node.heading ? (
                <div className="mb-4">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">{node.heading}</h2>
                </div>
            ) : null}

            <div className="overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm">
                <TableRenderer initial={node.table} />
            </div>
        </section>
    );
}
