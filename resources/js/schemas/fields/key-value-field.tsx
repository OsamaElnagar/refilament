import { useEffect, useState } from 'react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

interface KeyValueRow {
    key: string;
    value: string;
}

function toRows(value: unknown): KeyValueRow[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null)
        .map((row) => ({
            key: typeof row.key === 'string' ? row.key : '',
            value: typeof row.value === 'string' ? row.value : '',
        }));
}

/**
 * Key value field (mirrors Filament's KeyValue) — an editable table of
 * key/value rows. Rows can be added, removed and reordered; keys and values
 * are independently editable via `editableKeys()` / `editableValues()`. The
 * value is an array of `{ key, value }` objects.
 */
export default function KeyValueField({ node, value, error, onChange, formValues }: FieldProps) {
    const disabled = node.disabled ?? node.readOnly ?? false;
    const addable = node.addable !== false;
    const deletable = node.deletable !== false;
    const editableKeys = node.editableKeys !== false;
    const editableValues = node.editableValues !== false;
    const reorderable = node.reorderable === true;
    const keyLabel = node.keyLabel ?? 'Key';
    const valueLabel = node.valueLabel ?? 'Value';
    const addActionLabel = node.addActionLabel ?? 'Add';
    const keyPlaceholder = node.keyPlaceholder;
    const valuePlaceholder = node.valuePlaceholder;

    const [rows, setRows] = useState<KeyValueRow[]>(() => toRows(value));
    const [draggedIndex, setDraggedIndex] = useState<number | null>(null);

    const serialized = JSON.stringify(toRows(value));

    useEffect(() => {
        setRows(toRows(value));
    }, [serialized]);

    const commit = (nextRows: KeyValueRow[]) => {
        setRows(nextRows);
        onChange?.(nextRows);
    };

    const updateRow = (index: number, field: keyof KeyValueRow, nextValue: string) => {
        commit(rows.map((row, i) => (i === index ? { ...row, [field]: nextValue } : row)));
    };

    const addRow = () => {
        commit([...rows, { key: '', value: '' }]);
    };

    const removeRow = (index: number) => {
        commit(rows.filter((_, i) => i !== index));
    };

    const reorder = (from: number, to: number) => {
        if (from === to) {
            return;
        }

        const next = [...rows];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);
        commit(next);
    };

    const showActionColumns = (deletable || reorderable) && rows.length > 0;

    return (
        <div>
            <FieldHeader node={node} formValues={formValues} labelId={node.name} />

            <div className="overflow-hidden rounded-md border border-input">
                <table className="w-full border-collapse text-sm" aria-labelledby={node.name}>
                    <thead>
                        <tr className="border-b border-input bg-muted/50 text-left">
                            {reorderable && !disabled ? <th className="w-8" /> : null}
                            <th className="px-3 py-2 text-xs font-medium text-muted-foreground">{keyLabel}</th>
                            <th className="px-3 py-2 text-xs font-medium text-muted-foreground">{valueLabel}</th>
                            {deletable && !disabled ? <th className="w-8" /> : null}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={showActionColumns ? 4 : 2}
                                    className="px-3 py-4 text-center text-sm text-muted-foreground"
                                >
                                    No rows yet
                                </td>
                            </tr>
                        ) : (
                            rows.map((row, index) => (
                                <tr
                                    key={index}
                                    className="border-b border-input last:border-b-0"
                                    draggable={reorderable && !disabled}
                                    onDragStart={() => setDraggedIndex(index)}
                                    onDragOver={(event) => {
                                        if (reorderable && !disabled) {
                                            event.preventDefault();
                                        }
                                    }}
                                    onDrop={() => {
                                        if (reorderable && draggedIndex !== null) {
                                            reorder(draggedIndex, index);
                                            setDraggedIndex(null);
                                        }
                                    }}
                                >
                                    {reorderable && !disabled ? (
                                        <td className="px-1">
                                            <GripVertical className={cn('size-4 text-muted-foreground', reorderable && 'cursor-grab')} />
                                        </td>
                                    ) : null}
                                    <td className="px-1 py-1">
                                        <Input
                                            value={row.key}
                                            onChange={(event) => updateRow(index, 'key', event.target.value)}
                                            disabled={disabled || !editableKeys}
                                            placeholder={keyPlaceholder}
                                            aria-label={keyLabel}
                                            className="border-transparent bg-transparent shadow-none focus-visible:ring-0"
                                        />
                                    </td>
                                    <td className="px-1 py-1">
                                        <Input
                                            value={row.value}
                                            onChange={(event) => updateRow(index, 'value', event.target.value)}
                                            disabled={disabled || !editableValues}
                                            placeholder={valuePlaceholder}
                                            aria-label={valueLabel}
                                            className="border-transparent bg-transparent shadow-none focus-visible:ring-0"
                                        />
                                    </td>
                                    {deletable && !disabled ? (
                                        <td className="px-1 text-center">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-muted-foreground hover:text-destructive"
                                                onClick={() => removeRow(index)}
                                                aria-label={`Delete ${row.key || 'row'}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </td>
                                    ) : null}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {addable && !disabled ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-2"
                    onClick={addRow}
                >
                    <Plus className="size-4" />
                    {addActionLabel}
                </Button>
            ) : null}

            {error ? <p className="mt-0.5 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}