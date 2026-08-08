import type { ReactNode } from 'react';

import { renderEntryNode } from '@/infolists/registry';
import type { FieldNode } from '@/schemas/types';

interface InfolistRendererProps {
    schema: FieldNode[];
}

/**
 * Read-only record display (slice 3.3). Walks the infolist schema tree —
 * leaf entries render their server-resolved values, layouts (grid/section)
 * arrange them — with no form state, validation or submit. Mirrors the form
 * SchemaRenderer's tree walk, minus all interactivity.
 */
export function InfolistRenderer({ schema }: InfolistRendererProps) {
    const renderChildren = (nodes: FieldNode[]): ReactNode =>
        nodes.map((node, index) => renderEntryNode(node, index, renderChildren));

    return <dl className="space-y-6">{renderChildren(schema)}</dl>;
}
