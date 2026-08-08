import { evaluateExpression } from '@/schemas/expression';
import type { FieldNode } from '@/schemas/types';
import { flattenNodes } from '@/schemas/tree';

/**
 * Compute every `computed` field in a schema against the live form values,
 * in declaration order so a computed field can reference an earlier computed
 * field (subtotal → VAT → total, the Ahram invoice chain). Results are
 * rounded to two decimals — the same display the server stores, mirroring
 * Filament's numeric rounding. Non-numeric inputs cascade to null.
 */
export function computeComputedValues(nodes: FieldNode[], values: Record<string, unknown>): Record<string, unknown> {
    const computed: Record<string, unknown> = {};

    for (const node of flattenNodes(nodes)) {
        if (typeof node.computed !== 'string') {
            continue;
        }

        const result = evaluateExpression(node.computed, { ...values, ...computed });

        computed[node.name] = result === null ? null : Math.round(result * 100) / 100;
    }

    return computed;
}
