import type { FieldNode } from '@/schemas/types';

/**
 * Client-side visibility rules (slice 2.4, docs/CONTRACT.md).
 *
 * A node may declare `whenTruthy` / `whenFalsy`: sibling field names that must
 * be (respectively) all truthy / all falsy for the node to render. Rules are
 * pure client state — there is no round trip, and the server never needs to
 * remember anything between requests (mirrors Filament's `whenTruthy()` /
 * `whenFalsy()` from `CanBeHidden`, evaluated against `data` here).
 */

function isTruthy(value: unknown): boolean {
    if (value === undefined || value === null || value === '') {
        return false;
    }
    if (typeof value === 'boolean') {
        return value;
    }
    if (typeof value === 'number') {
        return value !== 0;
    }
    return Boolean(value);
}

/**
 * Whether a single node's own rules allow it to render, given the current
 * flat data. Layouts pass `true` here — their visibility is decided by
 * `nodeHasVisibleDescendant`, not their own fields.
 */
export function nodeIdIsVisible(node: FieldNode, data: Record<string, unknown>): boolean {
    if (Array.isArray(node.whenTruthy) && node.whenTruthy.length) {
        if (!node.whenTruthy.every((name) => isTruthy(data[name]))) {
            return false;
        }
    }

    if (Array.isArray(node.whenFalsy) && node.whenFalsy.length) {
        if (!node.whenFalsy.every((name) => !isTruthy(data[name]))) {
            return false;
        }
    }

    return true;
}

/**
 * Whether at least one descendant of a layout node is visible. A Section or
 * Grid whose rules hide every child renders nothing (a dead shell otherwise).
 */
export function nodeHasVisibleDescendant(
    node: FieldNode,
    data: Record<string, unknown>,
): boolean {
    if (!Array.isArray(node.schema) || node.schema.length === 0) {
        return false;
    }

    return node.schema.some((child) => {
        if (child.schema) {
            return nodeHasVisibleDescendant(child, data);
        }
        return nodeIdIsVisible(child, data);
    });
}

/**
 * Whether a node and, for layouts, its subtree should render.
 */
export function isNodeVisible(node: FieldNode, data: Record<string, unknown>): boolean {
    if (!nodeIdIsVisible(node, data)) {
        return false;
    }

    if (node.schema) {
        return nodeHasVisibleDescendant(node, data);
    }

    return true;
}