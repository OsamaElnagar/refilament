import { useState } from 'react';
import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Icon } from '@/components/icon';
import { cn } from '@/lib/utils';
import type { TableCellDisplay, TableCellPresentation } from '@/tables/types';

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

/** Tailwind text-align per serialized Alignment enum value. */
const ALIGNMENT_CLASSES: Record<string, string> = {
    start: 'text-start',
    left: 'text-left',
    center: 'text-center',
    end: 'text-end',
    right: 'text-right',
    justify: 'text-justify',
};

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
    /** Column presentation options, serialized on the definition. */
    presentation?: TableCellPresentation;
}

/**
 * Render one table cell. Plain columns render the bare value (or the column's
 * placeholder for empties); display columns render a Badge (with its per-record
 * color), an icon beside the value, and/or a link — all resolved server-side.
 * Column-level presentation (alignment, weight, font family, line-clamp,
 * tooltip, icon size/position, extra attributes) is applied from the
 * definition.
 */
export function Cell({ value, placeholder, presentation }: CellProps) {
    const display = toCellDisplay(value);
    const empty = display.value === null || display.value === undefined || display.value === '';

    const align = presentation?.alignment ? (ALIGNMENT_CLASSES[presentation.alignment] ?? '') : '';
    const weight = presentation?.weight ? `font-${presentation.weight}` : '';
    const family = presentation?.fontFamily ? `font-${presentation.fontFamily}` : '';
    const clamp = presentation?.lineClamp ? `line-clamp-${presentation.lineClamp}` : '';
    const iconSize = presentation?.iconSize ? (ICON_SIZE_CLASSES[presentation.iconSize] ?? 'size-3.5') : 'size-3.5';
    const iconAfter = presentation?.iconPosition === 'after';
    const cellAttrs = { title: presentation?.tooltip, ...presentation?.extraAttributes };

    // Specialized column kinds render their own primitives (badge list, image
    // stack, color swatches) instead of the scalar value. Only branch when the
    // cell actually carries the relevant array — a blank state falls through to
    // the normal placeholder rendering below.
    if (presentation?.kind === 'tags' && (display.tags?.length ?? 0) > 0) {
        return <TagsCell display={display} {...cellAttrs} />;
    }

    if (presentation?.kind === 'image' && (display.images?.length ?? 0) > 0) {
        return <ImageCell display={display} presentation={presentation} {...cellAttrs} />;
    }

    if (presentation?.kind === 'color' && (display.colors?.length ?? 0) > 0) {
        return <ColorCell display={display} copyable={presentation.copyable === true} {...cellAttrs} />;
    }

    const content = empty ? (
        <span className={cn('text-muted-foreground/60', align)} {...cellAttrs}>
            {placeholder ?? '—'}
        </span>
    ) : (
        <span className={cn('inline-flex items-center gap-1.5', align, weight, family, clamp)} {...cellAttrs}>
            {display.icon && !iconAfter ? <CellIcon name={display.icon} color={display.iconColor} size={iconSize} /> : null}
            <span>{String(display.value)}</span>
            {display.icon && iconAfter ? <CellIcon name={display.icon} color={display.iconColor} size={iconSize} /> : null}
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
            <CellIcon name={display.icon} color={display.iconColor} size={iconSize} />
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

function CellIcon({ name, color, size = 'size-3.5' }: { name: string; color?: string; size?: string }) {
    return (
        <Icon
            name={name}
            className={cn(
                size,
                'shrink-0',
                color ? (CELL_COLORS[color as keyof typeof CELL_COLORS] ?? CELL_COLORS.secondary) : 'text-muted-foreground',
            )}
        />
    );
}

/** Tags column — the cell state as a small badge list, with an overflow count. */
function TagsCell({ display, ...attrs }: { display: TableCellDisplay } & Record<string, unknown>) {
    const tags = display.tags ?? [];

    return (
        <span className="inline-flex flex-wrap items-center gap-1" {...attrs}>
            {tags.map((tag, index) => (
                <Badge key={index} variant="outline" className="py-0 font-normal">
                    {tag}
                </Badge>
            ))}
            {typeof display.remaining === 'number' && display.remaining > 0 ? (
                <span className="text-xs text-muted-foreground">+{display.remaining}</span>
            ) : null}
        </span>
    );
}

/** Image column — one or more thumbnails, optionally circular/square/stacked. */
function ImageCell({
    display,
    presentation,
    ...attrs
}: { display: TableCellDisplay; presentation: TableCellPresentation } & Record<string, unknown>) {
    const images = display.images ?? [];
    const stacked = presentation.stacked === true;
    const numericSize = typeof presentation.size === 'number' ? presentation.size : Number(presentation.size ?? NaN);
    const size = Number.isFinite(numericSize) ? numericSize : stacked ? 32 : 40;
    const ring = presentation.ring ?? 2;
    const overlap = presentation.overlap ?? 2;

    return (
        <span className="inline-flex items-center" {...attrs}>
            {images.map((src, index) => (
                <img
                    key={index}
                    src={src}
                    alt=""
                    loading="lazy"
                    className={cn(
                        'shrink-0 object-cover',
                        presentation.circular ? 'rounded-full' : 'rounded',
                        stacked ? 'ring-2 ring-background' : '',
                    )}
                    style={{
                        width: size,
                        height: size,
                        ...(stacked && index > 0 ? { marginLeft: -overlap, boxShadow: `0 0 0 ${ring}px hsl(var(--background))` } : {}),
                    }}
                />
            ))}
            {typeof display.remaining === 'number' && display.remaining > 0 ? (
                <span className="ml-1 text-xs text-muted-foreground">+{display.remaining}</span>
            ) : null}
        </span>
    );
}

/** Color column — color swatches; copyable swatches copy their value on click. */
function ColorCell({
    display,
    copyable,
    ...attrs
}: { display: TableCellDisplay; copyable: boolean } & Record<string, unknown>) {
    const colors = display.colors ?? [];
    const [copied, setCopied] = useState<number | null>(null);

    return (
        <span className="inline-flex items-center gap-1" {...attrs}>
            {colors.map((color, index) => (
                <button
                    key={index}
                    type="button"
                    disabled={!copyable}
                    aria-label={color}
                    title={copyable ? `Copy ${color}` : color}
                    onClick={() => {
                        if (!copyable) {
                            return;
                        }

                        void navigator.clipboard?.writeText(color);
                        setCopied(index);
                        window.setTimeout(() => setCopied(null), 1200);
                    }}
                    className={cn(
                        'relative size-5 rounded-sm',
                        copyable ? 'cursor-pointer' : 'cursor-default',
                    )}
                    style={{ backgroundColor: color }}
                >
                    {copied === index ? (
                        <Check className="absolute inset-0 m-auto size-3 text-white mix-blend-difference" />
                    ) : null}
                </button>
            ))}
        </span>
    );
}