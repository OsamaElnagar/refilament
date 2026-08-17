import { Cell } from '@/tables/cell';
import type { TableCellDisplay, TableCellPresentation } from '@/tables/types';
import type { EntryProps } from '@/infolists/registry';

/**
 * Text entry — a labeled read-only value (slice 3.3). The server resolves and
 * formats the value (money / date / numeric / ...) and any presentation
 * (badge / color / icon / url) before sending the node as sibling keys, so
 * this renderer assembles them into the table Cell's structured display shape
 * for a consistent look (identical badge/icon/link rendering as a cell).
 */
export function TextEntry({ node }: EntryProps) {
    const display: TableCellDisplay = {
        value: (node.value as string | number | null) ?? null,
        badge: node.badge === true,
        color: node.color as TableCellDisplay['color'],
        icon: node.icon as string | undefined,
        iconColor: node.iconColor as TableCellDisplay['iconColor'],
        url: node.url as string | undefined,
        openUrlInNewTab: node.openUrlInNewTab === true,
    };

    return (
        <div className="flex flex-col gap-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd className="text-sm text-foreground">
                <Cell
                    value={display}
                    placeholder={node.placeholder}
                    presentation={node as TableCellPresentation}
                />
            </dd>
        </div>
    );
}
