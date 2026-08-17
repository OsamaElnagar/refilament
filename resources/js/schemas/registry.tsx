import type { ComponentType, ReactNode } from 'react';

import type { FieldNode, SelectOption } from '@/schemas/types';

export interface FieldProps {
    node: FieldNode;
    value?: unknown;
    error?: string;
    /** True while the whole form is submitting (fields disable their controls). */
    disabled?: boolean;
    /** Options resolved for a `dependsOn` field, overriding `node.options`. */
    options?: SelectOption[];
    /** True while the resolve-options endpoint is fetching this field. */
    loading?: boolean;
    /** True while a live unique check (slice 2.5) is in flight for this field. */
    checking?: boolean;
    /** Report a value change upward to the form state. */
    onChange?: (value: unknown) => void;
    /**
     * Live form-wide values — lets a field evaluate client-side rules like
     * a hint action's `visibleWhenFilled` against sibling fields.
     */
    formValues?: Record<string, unknown>;
    /**
     * All current server validation errors, keyed by field name — the
     * repeater maps its row errors (`items.0.name`) onto the row fields.
     */
    errors?: Record<string, string[]>;
}

export type FieldComponent = ComponentType<FieldProps>;

/**
 * Layout components (grid, section, ...) wrap child nodes instead of editing
 * a value. They render their children by calling `renderChildren`.
 */
export interface LayoutProps {
    node: FieldNode;
    renderChildren: (nodes: FieldNode[]) => ReactNode;
}

export type LayoutComponent = ComponentType<LayoutProps>;

const registry = new Map<string, FieldComponent>();
const layoutRegistry = new Map<string, LayoutComponent>();

export function registerField(type: string, component: FieldComponent): void {
    registry.set(type, component);
}

export function getField(type: string): FieldComponent | undefined {
    return registry.get(type);
}

export function registerLayout(type: string, component: LayoutComponent): void {
    layoutRegistry.set(type, component);
}

export function getLayout(type: string): LayoutComponent | undefined {
    return layoutRegistry.get(type);
}
