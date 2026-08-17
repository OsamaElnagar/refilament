import type { EntryProps } from '@/infolists/registry';

interface KeyValueRow {
    key: string;
    value: string;
}

function toRows(value: unknown): KeyValueRow[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null)
        .map((row) => ({
            key: typeof row.key === 'string' ? row.key : String(row.key ?? ''),
            value: row.value == null ? '' : String(row.value),
        }));
}

/**
 * Key value entry — a read-only key/value table (Slice 3.9 / PLAN §2). The
 * server normalizes the value to `{ key, value }` rows, so this shares the
 * KeyValue field's visual (key/value column headers), minus all editing.
 */
export function KeyValueEntry({ node }: EntryProps) {
    const rows = toRows(node.value);
    const empty = rows.length === 0;

    return (
        <div className="flex flex-col gap-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd>
                {empty ? (
                    <span className="text-sm text-muted-foreground/60">{node.placeholder ?? '—'}</span>
                ) : (
                    <div className="overflow-hidden rounded-md border border-input">
                        <table className="w-full border-collapse text-sm" aria-label={node.label}>
                            <thead>
                                <tr className="border-b border-input bg-muted/50 text-left">
                                    <th scope="col" className="px-3 py-2 text-xs font-medium text-muted-foreground">
                                        {node.keyLabel ?? 'Key'}
                                    </th>
                                    <th scope="col" className="px-3 py-2 text-xs font-medium text-muted-foreground">
                                        {node.valueLabel ?? 'Value'}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => (
                                    <tr key={index} className="border-b border-input last:border-0">
                                        <th scope="row" className="px-3 py-1.5 text-left align-top font-medium text-muted-foreground">
                                            {row.key}
                                        </th>
                                        <td className="px-3 py-1.5 break-words">{row.value}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </dd>
        </div>
    );
}