import { useState } from 'react';
import { Check } from 'lucide-react';

import type { EntryProps } from '@/infolists/registry';

function toCode(value: unknown): string {
    if (typeof value === 'string') {
        return value;
    }

    if (value === null || value === undefined) {
        return '';
    }

    return JSON.stringify(value, null, 2);
}

/**
 * Code entry — a read-only `<pre><code>` block (Slice 3.9 / PLAN §2). Plain
 * syntax presentation (no highlighting in v1); optional `lineNumbers()` gutter
 * and a `copyable()` block that copies the value on click.
 */
export function CodeEntry({ node }: EntryProps) {
    const code = toCode(node.value);
    const empty = code === '';

    const lineNumbers = node.lineNumbers === true;
    const copyable = node.copyable === true;

    const [copied, setCopied] = useState(false);

    const lines = code.split('\n');

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
            <dt className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                <span>{node.label}</span>
                {typeof node.language === 'string' && node.language ? (
                    <code className="rounded bg-muted px-1 py-0.5 font-mono text-[10px] normal-case">{node.language}</code>
                ) : null}
            </dt>
            <dd>
                <pre
                    role={copyable ? 'button' : undefined}
                    tabIndex={copyable ? 0 : undefined}
                    onClick={() => {
                        if (!copyable) {
                            return;
                        }

                        void navigator.clipboard?.writeText(code);
                        setCopied(true);
                        window.setTimeout(() => setCopied(false), 1200);
                    }}
                    onKeyDown={(event) => {
                        if (copyable && (event.key === 'Enter' || event.key === ' ')) {
                            event.preventDefault();
                            void navigator.clipboard?.writeText(code);
                            setCopied(true);
                            window.setTimeout(() => setCopied(false), 1200);
                        }
                    }}
                    title={copyable ? 'Copy code' : undefined}
                    className={`overflow-x-auto rounded-md border bg-muted p-3 font-mono text-xs leading-relaxed ${copyable ? 'cursor-pointer' : ''}`}
                >
                    <code>
                        {lineNumbers
                            ? lines.map((line, i) => (
                                  <div key={i} className="table-row">
                                      <span className="table-cell select-none pr-3 text-right text-muted-foreground/50">{i + 1}</span>
                                      <span className="table-cell whitespace-pre">{line}</span>
                                  </div>
                              ))
                            : code}
                    </code>
                </pre>
                {copied ? (
                    <span className="mt-1 inline-flex items-center gap-1 text-xs text-emerald-600">
                        <Check className="size-3" /> Copied
                    </span>
                ) : null}
            </dd>
        </div>
    );
}