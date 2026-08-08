import type { ComponentType, ReactNode } from 'react';

import { TextEntry } from '@/infolists/entries/text-entry';
import { getLayout } from '@/schemas/registry';
import type { FieldNode } from '@/schemas/types';

export interface EntryProps {
    node: FieldNode;
}

export type EntryComponent = ComponentType<EntryProps>;

const registry = new Map<string, EntryComponent>();

export function registerEntry(type: string, component: EntryComponent): void {
    registry.set(type, component);
}

export function getEntry(type: string): EntryComponent | undefined {
    return registry.get(type);
}

/**
 * Render a read-only infolist node. Layouts (grid/section/...) delegate back
 * to the renderer via `renderChildren`; leaf entries render their already
 * resolved, already formatted server-side value. Mirrors the form
 * SchemaRenderer's layout/leaf split, but read-only — no onChange, no submit.
 */
export function renderEntryNode(
    node: FieldNode,
    index: number,
    renderChildren: (nodes: FieldNode[]) => ReactNode,
): ReactNode {
    const Layout = getLayout(node.type);

    if (Layout) {
        return (
            <Layout
                key={node.name ?? `${node.type}-${index}`}
                node={node}
                renderChildren={renderChildren}
            />
        );
    }

    const Entry = getEntry(node.type) ?? TextEntry;

    return <Entry key={node.name ?? `${node.type}-${index}`} node={node} />;
}
