import { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, ChevronUp, ChevronsDownUp, ChevronsUpDown, Copy, GripVertical, Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { getField, getLayout } from '@/schemas/registry';
import type { FieldProps } from '@/schemas/registry';
import type { FieldNode } from '@/schemas/types';
import { isNodeVisible } from '@/schemas/visibility';
import DebugField from '@/schemas/fields/debug-field';

interface RepeaterRow {
    [key: string]: unknown;
}

interface RowState {
    id: number;
    data: RepeaterRow;
}

// Tailwind v4 scans for literal class names, so the dynamic grid-cols classes
// live in a lookup map (the PHP builder clamps to the same 1..6 domain).
const GRID_COLS: Record<number, string> = {
    1: 'md:grid-cols-1',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
    4: 'md:grid-cols-4',
    5: 'md:grid-cols-5',
    6: 'md:grid-cols-6',
};

/** Substitute `{field}` tokens in an item-label template from a row's state. */
function renderItemLabel(template: string | undefined, row: RepeaterRow, index: number, itemNumbers: boolean): string {
    let label = template ?? String(index + 1).padStart(2, '0');

    if (template) {
        label = template.replace(/\{([^}]+)\}/g, (_match, field: string) => {
            const value = row[field];

            if (value === null || value === undefined || value === '') {
                return '';
            }

            return String(value);
        });
    }

    return itemNumbers ? `${index + 1}. ${label}` : label;
}

/**
 * Repeater field (mirrors Filament's Repeater) — the form value is an array
 * of rows; each row renders the row schema with its own value object and
 * writes changes back into the array. Rows carry a stable client id so
 * reorder/clone/remove keep their collapsed state. Add/remove/clone and
 * drag or button reordering are all pure client state; min/max cap add/remove.
 * Row errors map from the server's `{name}.{index}.{field}` keys.
 */
export default function RepeaterField({ node, value, error, onChange, errors }: FieldProps) {
    const fieldName = node.name ?? '';
    const rowSchema = (node.schema ?? []) as FieldNode[];
    const minItems = typeof node.minItems === 'number' ? node.minItems : undefined;
    const maxItems = typeof node.maxItems === 'number' ? node.maxItems : undefined;
    const grid = typeof node.grid === 'number' ? node.grid : undefined;
    const gridClass = grid && grid > 1 ? GRID_COLS[Math.min(grid, 6)] ?? GRID_COLS[1] : undefined;
    const collapsible = node.collapsible === true;
    const startCollapsed = node.collapsed === true;
    const cloneable = node.cloneable === true;
    const addable = node.addable !== false;
    const deletable = node.deletable !== false;
    const reorderWithDrag = node.reorderableWithDragAndDrop !== false && node.reorderable !== false;
    const reorderWithButtons = node.reorderableWithButtons === true && node.reorderable !== false;
    const itemNumbers = node.itemNumbers === true;
    const itemHeaders = node.itemHeaders !== false;
    const disabled = node.disabled ?? node.readOnly ?? false;
    const addLabel = typeof node.addActionLabel === 'string' ? node.addActionLabel : undefined;
    const itemLabelTemplate = typeof node.itemLabel === 'string' ? node.itemLabel : undefined;

    const idCounter = useRef(0);
    const lastEmitted = useRef<string>('');

    const initRows = (rowsValue: unknown): RowState[] => {
        const source = Array.isArray(rowsValue) ? (rowsValue as RepeaterRow[]) : [];

        return source.map((data) => ({ id: ++idCounter.current, data: { ...data } }));
    };

    const [rows, setRows] = useState<RowState[]>(() => initRows(value));
    const [collapsedIds, setCollapsedIds] = useState<Set<number>>(() =>
        startCollapsed ? new Set(initRows(value).map((row) => row.id)) : new Set(),
    );
    const [draggedId, setDraggedId] = useState<number | null>(null);

    // Rebuild local rows only when the value changes outside our own commits.
    const serialized = useMemo(() => JSON.stringify(value ?? []), [value]);

    useEffect(() => {
        if (serialized === lastEmitted.current) {
            return;
        }

        const fresh = initRows(value);

        setRows(fresh);

        if (startCollapsed) {
            setCollapsedIds(new Set(fresh.map((row) => row.id)));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serialized]);

    const canAdd = (): boolean => addable && (maxItems === undefined || rows.length < maxItems);
    const canRemove = (): boolean => deletable && (minItems === undefined || rows.length > minItems);

    const commit = (nextRows: RowState[]) => {
        setRows(nextRows);
        const emitted = nextRows.map((row) => row.data);
        lastEmitted.current = JSON.stringify(emitted);
        onChange?.(emitted);
    };

    const setRowValue = (id: number, rowFieldName: string, rowFieldValue: unknown): void => {
        commit(rows.map((row) => (row.id === id ? { ...row, data: { ...row.data, [rowFieldName]: rowFieldValue } } : row)));
    };

    const removeRow = (id: number): void => {
        if (!canRemove()) {
            return;
        }

        const next = rows.filter((row) => row.id !== id);

        setCollapsedIds((current) => {
            const nextCollapsed = new Set(current);
            nextCollapsed.delete(id);

            return nextCollapsed;
        });

        commit(next);
    };

    const addRow = (): void => {
        if (!canAdd()) {
            return;
        }

        const data: RepeaterRow = {};

        for (const field of rowSchema) {
            if (field.name) {
                data[field.name] = field.default ?? '';
            }
        }

        commit([...rows, { id: ++idCounter.current, data }]);
    };

    const cloneRow = (id: number): void => {
        if (!cloneable) {
            return;
        }

        const source = rows.find((row) => row.id === id);

        if (!source) {
            return;
        }

        commit([...rows, { id: ++idCounter.current, data: structuredClone(source.data) }]);
    };

    const moveRow = (id: number, direction: -1 | 1): void => {
        const index = rows.findIndex((row) => row.id === id);
        const target = index + direction;

        if (index < 0 || target < 0 || target >= rows.length) {
            return;
        }

        const next = [...rows];
        const [moved] = next.splice(index, 1);
        next.splice(target, 0, moved);
        commit(next);
    };

    const reorderByIds = (fromId: number, toId: number): void => {
        if (fromId === toId) {
            return;
        }

        const fromIndex = rows.findIndex((row) => row.id === fromId);
        const toIndex = rows.findIndex((row) => row.id === toId);

        if (fromIndex < 0 || toIndex < 0) {
            return;
        }

        const next = [...rows];
        const [moved] = next.splice(fromIndex, 1);
        next.splice(toIndex, 0, moved);
        commit(next);
        setDraggedId(null);
    };

    const toggleCollapsed = (id: number): void => {
        setCollapsedIds((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const setAllCollapsed = (collapsed: boolean): void => {
        setCollapsedIds(collapsed ? new Set(rows.map((row) => row.id)) : new Set());
    };

    const renderRowNode = (node: FieldNode, row: RepeaterRow): ReactNode => {
        const Layout = getLayout(node.type);

        if (Layout) {
            return (
                <Layout
                    key={node.name ?? node.type}
                    node={node}
                    renderChildren={(nodes) => nodes.filter((n) => isNodeVisible(n, row)).map((n) => renderRowNode(n, row))}
                />
            );
        }

        const Field = getField(node.type) ?? DebugField;
        const rowFieldName = node.name ?? '';
        const rowIndex = rows.findIndex((rowState) => rowState.data === row);

        return (
            <Field
                key={rowFieldName}
                node={node}
                value={row[rowFieldName]}
                error={errors?.[`${fieldName}.${rowIndex}.${rowFieldName}`]?.[0]}
                onChange={(rowFieldValue) => setRowValueById(rowFieldName, rowFieldValue, row)}
                formValues={row}
            />
        );
    };

    const setRowValueById = (rowFieldName: string, rowFieldValue: unknown, row: RepeaterRow): void => {
        const rowState = rows.find((state) => state.data === row);

        if (rowState) {
            setRowValue(rowState.id, rowFieldName, rowFieldValue);
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <label className="text-sm font-medium leading-none text-foreground">
                    {node.label ?? fieldName}
                    {node.required ? <span className="ml-0.5 text-destructive">*</span> : null}
                </label>

                {collapsible && rows.length > 1 ? (
                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                        <button
                            type="button"
                            onClick={() => setAllCollapsed(true)}
                            className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-accent hover:text-foreground"
                        >
                            <ChevronsDownUp className="size-3.5" />
                            Collapse all
                        </button>
                        <button
                            type="button"
                            onClick={() => setAllCollapsed(false)}
                            className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-accent hover:text-foreground"
                        >
                            <ChevronsUpDown className="size-3.5" />
                            Expand all
                        </button>
                    </div>
                ) : null}

                <Button type="button" variant="outline" size="sm" onClick={addRow} disabled={!canAdd()}>
                    <Plus className="size-3.5" />
                    {addLabel ?? `Add ${node.label ? String(node.label).toLowerCase() : 'item'}`}
                </Button>
            </div>

            {rows.length === 0 ? <p className="text-sm text-muted-foreground">No items yet.</p> : null}

            <div className="space-y-2">
                {rows.map((row, index) => (
                    <RepeaterRowCard
                        key={row.id}
                        isFirst={index === 0}
                        isLast={index === rows.length - 1}
                        row={row.data}
                        rowSchema={rowSchema}
                        collapsible={collapsible}
                        collapsed={collapsedIds.has(row.id)}
                        itemHeaders={itemHeaders}
                        gridClass={gridClass}
                        itemLabel={renderItemLabel(itemLabelTemplate, row.data, index, itemNumbers)}
                        reorderWithDrag={reorderWithDrag && !disabled}
                        reorderWithButtons={reorderWithButtons && !disabled}
                        cloneable={cloneable && !disabled}
                        deletable={canRemove() && !disabled}
                        onToggleCollapsed={() => toggleCollapsed(row.id)}
                        onRemove={() => removeRow(row.id)}
                        onClone={() => cloneRow(row.id)}
                        onMove={(direction) => moveRow(row.id, direction)}
                        onDragStart={() => setDraggedId(row.id)}
                        onDragOver={(event) => {
                            if (reorderWithDrag && !disabled) {
                                event.preventDefault();
                            }
                        }}
                        onDrop={() => {
                            if (draggedId !== null && reorderWithDrag && !disabled) {
                                reorderByIds(draggedId, row.id);
                            }
                        }}
                        renderRowNode={renderRowNode}
                    />
                ))}
            </div>

            {error ? <p className="mt-1 text-sm text-destructive">{error}</p> : null}
            {node.helperText ? <p className="mt-1 text-xs text-muted-foreground">{node.helperText}</p> : null}
        </div>
    );
}

function RepeaterRowCard({
    isFirst,
    isLast,
    row,
    rowSchema,
    collapsible,
    collapsed,
    itemHeaders,
    gridClass,
    itemLabel,
    reorderWithDrag,
    reorderWithButtons,
    cloneable,
    deletable,
    onToggleCollapsed,
    onRemove,
    onClone,
    onMove,
    onDragStart,
    onDragOver,
    onDrop,
    renderRowNode,
}: {
    isFirst: boolean;
    isLast: boolean;
    row: RepeaterRow;
    rowSchema: FieldNode[];
    collapsible: boolean;
    collapsed: boolean;
    itemHeaders: boolean;
    gridClass?: string;
    itemLabel: string;
    reorderWithDrag: boolean;
    reorderWithButtons: boolean;
    cloneable: boolean;
    deletable: boolean;
    onToggleCollapsed: () => void;
    onRemove: () => void;
    onClone: () => void;
    onMove: (direction: -1 | 1) => void;
    onDragStart: () => void;
    onDragOver: (event: React.DragEvent) => void;
    onDrop: () => void;
    renderRowNode: (node: FieldNode, row: RepeaterRow) => ReactNode;
}) {
    return (
        <div
            draggable={reorderWithDrag}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDrop={onDrop}
            className="rounded-lg border border-border"
        >
            {itemHeaders ? (
                <div className="flex items-center justify-between gap-2 border-b border-border/60 px-3 py-2">
                    <div className="flex min-w-0 items-center gap-1.5">
                        {reorderWithDrag ? (
                            <GripVertical
                                className="size-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing"
                                aria-hidden="true"
                            />
                        ) : null}

                        {collapsible ? (
                            <button
                                type="button"
                                onClick={onToggleCollapsed}
                                aria-expanded={!collapsed}
                                className="flex items-center gap-1 truncate text-sm font-medium text-foreground hover:text-primary"
                            >
                                <ChevronDown className={cn('size-3.5 shrink-0 transition-transform', collapsed ? '-rotate-90' : '')} />
                                <span className="truncate">{itemLabel}</span>
                            </button>
                        ) : (
                            <span className="truncate text-sm font-medium text-foreground">{itemLabel}</span>
                        )}
                    </div>

                    <div className="flex shrink-0 items-center gap-0.5">
                        {reorderWithButtons ? (
                            <>
                                <button
                                    type="button"
                                    onClick={() => onMove(-1)}
                                    disabled={isFirst}
                                    aria-label="Move up"
                                    className="rounded p-1 text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <ChevronUp className="size-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => onMove(1)}
                                    disabled={isLast}
                                    aria-label="Move down"
                                    className="rounded p-1 text-muted-foreground transition hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <ChevronDown className="size-4" />
                                </button>
                            </>
                        ) : null}

                        {cloneable ? (
                            <button
                                type="button"
                                onClick={onClone}
                                aria-label="Clone row"
                                className="rounded p-1 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                            >
                                <Copy className="size-4" />
                            </button>
                        ) : null}

                        {deletable ? (
                            <button
                                type="button"
                                onClick={onRemove}
                                aria-label="Remove row"
                                className="rounded p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive disabled:pointer-events-none disabled:opacity-40"
                            >
                                <X className="size-4" />
                            </button>
                        ) : null}
                    </div>
                </div>
            ) : null}

            {!collapsed ? (
                <div className={cn('p-3', gridClass)}>{rowSchema.map((node) => renderRowNode(node, row))}</div>
            ) : null}
        </div>
    );
}