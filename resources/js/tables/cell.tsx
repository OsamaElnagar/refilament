import {
    Check,
    CircleCheck,
    CircleX,
    Clock,
    Eye,
    Globe,
    Link2,
    Lock,
    Mail,
    Pencil,
    Phone,
    Star,
    Tag,
    Trash2,
    TriangleAlert,
    User,
    Users,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { TableCellDisplay } from '@/tables/types';

/** Tailwind text classes per color, over the default cell foreground. */
const CELL_COLORS = {
    primary: 'text-primary',
    secondary: 'text-muted-foreground',
    success: 'text-emerald-600 dark:text-emerald-400',
    danger: 'text-rose-600 dark:text-rose-400',
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-600 dark:text-sky-400',
} as const;

/** Badge variant classes per color (shadcn Badge has no color variants, so we
 * layer color onto the outline variant so the pill is always legible). */
const BADGE_COLORS = {
    primary: 'border-primary/30 bg-primary/10 text-primary',
    secondary: 'border-border/70 bg-muted text-muted-foreground',
    success: 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    danger: 'border-rose-600/30 bg-rose-600/10 text-rose-700 dark:text-rose-400',
    warning: 'border-amber-600/30 bg-amber-600/10 text-amber-700 dark:text-amber-400',
    info: 'border-sky-600/30 bg-sky-600/10 text-sky-700 dark:text-sky-400',
} as const;

/**
 * A small lookup of the icon keys we support in cells. The server resolves
 * the icon name per record; the renderer maps well-known keys to lucide
 * icons and drops unknown ones gracefully.
 */
const ICONS: Record<string, LucideIcon> = {
    check: Check,
    'check-circle': CircleCheck,
    x: X,
    'x-circle': CircleX,
    globe: Globe,
    mail: Mail,
    phone: Phone,
    user: User,
    users: Users,
    link: Link2,
    star: Star,
    clock: Clock,
    lock: Lock,
    pencil: Pencil,
    trash: Trash2,
    eye: Eye,
    alert: TriangleAlert,
    tag: Tag,
};

export { ICONS };

/**
 * Normalize a raw cell to a structured display (Slice 2.1). Plain columns
 * ship a scalar; display columns ship a { value, badge?, color?, icon?, url? }
 * object. This collapses both to one shape for the renderer.
 */
export function toCellDisplay(value: unknown): TableCellDisplay {
    if (
        value !== null &&
        typeof value === 'object' &&
        'value' in (value as Record<string, unknown>)
    ) {
        return value as TableCellDisplay;
    }

    return { value: (value as string | number | null) ?? null };
}

/** The scalar display value of a cell (unwraps structured cells). */
export function cellValue(value: unknown): string | number | null {
    return toCellDisplay(value).value;
}

interface CellProps {
    value: unknown;
    placeholder?: string;
}

/**
 * Render one table cell. Plain columns render the bare value (or the column's
 * placeholder for empties); display columns render a Badge (with its per-record
 * color), an icon beside the value, and/or a link — all resolved server-side.
 */
export function Cell({ value, placeholder }: CellProps) {
    const display = toCellDisplay(value);
    const empty = display.value === null || display.value === undefined || display.value === '';

    const content = empty ? (
        <span className="text-muted-foreground/60">{placeholder ?? '—'}</span>
    ) : (
        <span className="inline-flex items-center gap-1.5">
            {display.icon ? <CellIcon name={display.icon} color={display.iconColor} /> : null}
            <span>{String(display.value)}</span>
        </span>
    );

    // A link cell wraps only the value (never the empty placeholder).
    const wrapped = !empty && display.url ? (
        <a
            href={display.url}
            target={display.openUrlInNewTab ? '_blank' : undefined}
            rel={display.openUrlInNewTab ? 'noreferrer' : undefined}
            className="text-indigo-600 transition hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
        >
            {content}
        </a>
    ) : (
        content
    );

    if (!empty && display.badge) {
        const color = (display.color ?? 'secondary') as keyof typeof BADGE_COLORS;
        const icon = display.icon ? (
            <CellIcon name={display.icon} color={display.iconColor} />
        ) : null;

        return (
            <Badge variant="outline" className={cn('py-0.5 font-medium', BADGE_COLORS[color] ?? BADGE_COLORS.secondary)}>
                {icon}
                {String(display.value)}
            </Badge>
        );
    }

    if (display.color && !empty) {
        return (
            <span className={cn('font-medium', CELL_COLORS[(display.color as keyof typeof CELL_COLORS)] ?? CELL_COLORS.secondary)}>
                {wrapped}
            </span>
        );
    }

    return wrapped;
}

function CellIcon({ name, color }: { name: string; color?: string }) {
    const Icon = ICONS[name];

    if (!Icon) {
        return null;
    }

    return (
        <Icon
            className={cn(
                'size-3.5 shrink-0',
                color ? (CELL_COLORS[color as keyof typeof CELL_COLORS] ?? CELL_COLORS.secondary) : 'text-muted-foreground',
            )}
            aria-hidden="true"
        />
    );
}