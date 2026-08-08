import type { FieldProps } from '@/schemas/registry';

/**
 * Fallback renderer: shows the raw serialized node so unregistered types are
 * visible instead of silently breaking the render.
 */
export default function DebugField({ node }: FieldProps) {
    return (
        <div className="rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4">
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <span className="text-sm font-medium text-zinc-700">{node.label}</span>
                <code className="rounded bg-zinc-200 px-1.5 py-0.5 text-[11px] text-zinc-600">
                    {node.type}
                </code>
            </div>

            <pre className="max-h-48 overflow-auto rounded-md bg-white p-2 text-[11px] leading-relaxed text-zinc-500">
                {JSON.stringify(node, null, 2)}
            </pre>
        </div>
    );
}
