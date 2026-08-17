import type { LayoutProps } from '@/schemas/registry';
import { getViewComponent } from '@/schemas/views';

/**
 * Embedded view node (the embedded-React slice) — renders the consumer-
 * registered React component named by `view` with the server-computed
 * `viewData` as its props. Falls back to a neutral placeholder when the key
 * isn't registered, so a missing consumer component never breaks the page.
 */
export default function ViewLayout({ node }: LayoutProps) {
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
