import type { EntryProps } from '@/infolists/registry';
import { getViewComponent } from '@/schemas/views';

/**
 * View entry — an embedded React component inside an infolist (Slice 3.9 /
 * PLAN §2). The infolist counterpart of the form tree's `View` node: `view` is
 * the key the client resolves through its view-component registry
 * (`registerViewComponent()`), `viewData` is passed as the component's props.
 */
export function ViewEntry({ node }: EntryProps) {
    const key = typeof node.view === 'string' ? node.view : '';
    const data = (node.viewData ?? {}) as Record<string, unknown>;
    const Component = key ? getViewComponent(key) : undefined;

    if (!Component) {
        return (
            <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                Embedded view <code className="font-mono text-xs">{key || '(no key)'}</code> — register a React
                component for it via <code className="font-mono text-xs">registerViewComponent()</code>.
            </div>
        );
    }

    return <Component {...data} />;
}