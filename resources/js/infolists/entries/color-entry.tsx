import { useState } from 'react';
import { Check } from 'lucide-react';

import type { EntryProps } from '@/infolists/registry';
import { cn } from '@/lib/utils';

function toColors(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.filter((item): item is string => typeof item === 'string');
    }

    return typeof value === 'string' && value ? [value] : [];
}

/**
 * Color entry — one or more color swatches (slice 3.9 / PLAN §2). The value is
 * the color(s) themselves; a `copyable()` entry copies the value (or the
 * `copyableState` override) to the clipboard on click, with a checkmark flash
 * mirroring the table's color cell.
 */
export function ColorEntry({ node }: EntryProps) {
    const colors = toColors(node.value);
    const empty = colors.length === 0;

    const copyable = node.copyable === true;
    const copyableState = typeof node.copyableState === 'string' ? node.copyableState : null;
    const copyMessage = typeof node.copyMessage === 'string' ? node.copyMessage : 'Copied!';

    const [copied, setCopied] = useState<number | null>(null);

    if (empty) {
        return (
            <div className="flex flex-col gap-1">
                <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
                <dd className="text-sm text-muted-foreground/60">{node.placeholder ?? '—'}</dd>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{node.label}</dt>
            <dd className="inline-flex flex-wrap items-center gap-1">
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

                            void navigator.clipboard?.writeText(copyableState ?? color);
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
                        <span className="sr-only">{copyMessage}</span>
                    </button>
                ))}
            </dd>
        </div>
    );
}