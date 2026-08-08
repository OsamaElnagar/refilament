import type { FieldNode } from '@/schemas/types';

/**
 * Flatten a nested schema tree (layouts wrap children under `schema`) into a
 * single list of every node, so tree-wide concerns like dependent-options
 * watching can iterate fields regardless of how deep they are nested.
 */
export function flattenNodes(nodes: FieldNode[]): FieldNode[] {
    return nodes.flatMap((node) =>
        node.schema?.length ? [node, ...flattenNodes(node.schema)] : [node],
    );
}
