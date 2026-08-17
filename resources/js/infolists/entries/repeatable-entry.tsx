import type { EntryProps } from '@/infolists/registry';
import { renderEntryNode } from '@/infolists/registry';
import type { ReactNode } from 'react';

/**
 * Repeatable entry — a read-only list (slice: RepeatableEntry / PLAN §3). The
 * state is an array of items; each item renders through the declared child
 * entry schema, already resolved server-side against that item's data (so this
 * is purely presentational — no editing, no Livewire). Rendered as a bordered
 * card per item, mirroring Filament's stacked RepeatableEntry (the `table()`
 * layout is deferred for v1).
 */
export function RepeatableEntry({ node }: EntryProps) {
    const items = node.items ?? [];
    const empty = items.length === 0;

    return (
        <div className="flex flex-col gap-2">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd>
                {empty ? (
                    <span className="text-sm text-muted-foreground/60">{node.placeholder ?? '—'}</span>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {items.map((itemNodes, itemIndex) => (
                            <li key={itemIndex} className="rounded-md border border-input bg-muted/20 p-3">
                                <dl className="flex flex-col gap-2">
                                    {itemNodes.map((itemNode, entryIndex) =>
                                        renderEntryNode(itemNode, entryIndex, () => null as ReactNode),
                                    )}
                                </dl>
                            </li>
                        ))}
                    </ul>
                )}
            </dd>
        </div>
    );
}