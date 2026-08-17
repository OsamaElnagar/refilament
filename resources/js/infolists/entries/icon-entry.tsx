import { Icon } from '@/components/icon';
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

/** Tailwind icon size per serialized IconSize enum value. */
const ICON_SIZE_CLASSES: Record<string, string> = {
    xs: 'size-3',
    sm: 'size-3.5',
    md: 'size-4',
    lg: 'size-5',
    xl: 'size-6',
    '2xl': 'size-8',
};

/**
 * Icon entry — a boolean-ish value rendered as a single icon (slice 3.3).
 * A truthy state shows the configured icon in its color; a false/empty state
 * shows the placeholder. Mirrors Filament's IconEntry / boolean idiom.
 */
export function IconEntry({ node }: EntryProps) {
    const empty = node.value === null || node.value === undefined || node.value === false || node.value === '';

    const iconSize = node.iconSize ? (ICON_SIZE_CLASSES[node.iconSize as string] ?? 'size-5') : 'size-5';
    const cellAttrs = {
        title: node.tooltip as string | undefined,
        ...(node.extraAttributes as Record<string, string> | undefined),
    };

    if (empty || !node.icon) {
        return (
            <div className="flex flex-col gap-1" {...cellAttrs}>
                <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
                <dd className="text-sm text-muted-foreground/60">{node.placeholder ?? '—'}</dd>
            </div>
        );
    }

    const color = (node.iconColor as TableCellDisplay['iconColor']) ?? 'secondary';

    return (
        <div className="flex flex-col gap-1" {...cellAttrs}>
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd>
                <Icon
                    name={node.icon as string}
                    className={cn(
                        iconSize,
                        'shrink-0',
                        CELL_COLORS[color] ?? CELL_COLORS.secondary,
                    )}
                />
            </dd>
        </div>
    );
}