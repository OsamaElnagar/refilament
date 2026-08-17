import type { EntryProps } from '@/infolists/registry';
import { cn } from '@/lib/utils';

function toImages(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.filter((item): item is string => typeof item === 'string');
    }

    return typeof value === 'string' && value ? [value] : [];
}

/**
 * Image entry — one or more image thumbnails (Slice 3.9 / PLAN §2). The value
 * is a single URL or a list of them. Optional `circular()` / `square()`
 * cropping, `stacked()` avatar overlap with a `ring()`, and a `limit()` image
 * cap with a "+N" overflow count.
 */
export function ImageEntry({ node }: EntryProps) {
    const images = toImages(node.value);
    const empty = images.length === 0;

    const size = typeof node.size === 'number' && node.size > 0 ? node.size : undefined;
    const circular = node.circular === true;
    const square = node.square === true;
    const stacked = node.stacked === true;
    const ring = typeof node.ring === 'number' ? node.ring : 2;
    const limit = typeof node.limit === 'number' && node.limit > 0 ? node.limit : undefined;

    const visible = limit ? images.slice(0, limit) : images;
    const remaining = limit ? Math.max(0, images.length - limit) : 0;

    if (empty) {
        return (
            <div className="flex flex-col gap-1">
                <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
                <dd className="text-sm text-muted-foreground/60">{node.placeholder ?? '—'}</dd>
            </div>
        );
    }

    const px = size ?? (stacked ? 32 : 40);
    const overlap = 2;

    return (
        <div className="flex flex-col gap-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd className={cn('inline-flex flex-wrap items-center gap-1', stacked && 'flex-nowrap')}>
                {visible.map((src, index) => (
                    <img
                        key={index}
                        src={src}
                        alt=""
                        loading="lazy"
                        className={cn(
                            'shrink-0 object-cover',
                            circular || square ? (circular ? 'rounded-full' : 'rounded') : 'rounded',
                            stacked ? 'ring-2 ring-background' : '',
                        )}
                        style={{
                            width: px,
                            height: px,
                            ...(stacked && index > 0 ? { marginLeft: -overlap } : {}),
                            ...(stacked ? { boxShadow: `0 0 0 ${ring}px hsl(var(--background))` } : {}),
                        }}
                    />
                ))}
                {remaining > 0 ? <span className="text-xs text-muted-foreground">+{remaining}</span> : null}
            </dd>
        </div>
    );
}