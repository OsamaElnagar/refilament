import { ICONS } from '@/tables/cell';
import type { TableCellDisplay } from '@/tables/types';
import { cn } from '@/lib/utils';
import type { EntryProps } from '@/infolists/registry';

const CELL_COLORS = {
    primary: 'text-primary',
    secondary: 'text-muted-foreground',
    success: 'text-emerald-600 dark:text-emerald-400',
    danger: 'text-rose-600 dark:text-rose-400',
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-600 dark:text-sky-400',
} as const;

/**
 * Icon entry — a boolean-ish value rendered as a single icon (slice 3.3).
 * A truthy state shows the configured icon in its color; a false/empty state
 * shows the placeholder. Mirrors Filament's IconEntry / boolean idiom.
 */
export function IconEntry({ node }: EntryProps) {
    const empty = node.value === null || node.value === undefined || node.value === false || node.value === '';

    if (empty || !node.icon) {
        return (
            <div className="flex flex-col gap-1">
                <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
                <dd className="text-sm text-muted-foreground/60">{node.placeholder ?? '—'}</dd>
            </div>
        );
    }

    const Icon = ICONS[node.icon as string];

    const color = (node.iconColor as TableCellDisplay['iconColor']) ?? 'secondary';

    return (
        <div className="flex flex-col gap-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd>
                {Icon ? (
                    <Icon
                        className={cn(
                            'size-5 shrink-0',
                            CELL_COLORS[color] ?? CELL_COLORS.secondary,
                        )}
                        aria-hidden="true"
                    />
                ) : (
                    <span className="text-sm text-foreground">{String(node.value)}</span>
                )}
            </dd>
        </div>
    );
}