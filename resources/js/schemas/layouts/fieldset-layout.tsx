import type { LayoutProps } from '@/schemas/registry';
import type { FieldNode } from '@/schemas/types';

const GRID_COLS: Record<number, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
    5: 'grid-cols-5',
    6: 'grid-cols-6',
    7: 'grid-cols-7',
    8: 'grid-cols-8',
    9: 'grid-cols-9',
    10: 'grid-cols-10',
    11: 'grid-cols-11',
    12: 'grid-cols-12',
};

const COL_SPANS: Record<number, string> = {
    1: 'col-span-1',
    2: 'col-span-2',
    3: 'col-span-3',
    4: 'col-span-4',
    5: 'col-span-5',
    6: 'col-span-6',
    7: 'col-span-7',
    8: 'col-span-8',
    9: 'col-span-9',
    10: 'col-span-10',
    11: 'col-span-11',
    12: 'col-span-12',
};

function gridChildren(node: FieldNode, renderChildren: LayoutProps['renderChildren']) {
    const columns = Math.min(Math.max(node.columns ?? 2, 1), 12);
    const gridClass = GRID_COLS[columns] ?? 'grid-cols-2';

    return (
        <div className={`grid gap-6 ${gridClass}`}>
            {node.schema?.map((child, index) => (
                <div
                    key={child.name ?? `${node.type}-${index}`}
                    className={child.columnSpan ? (COL_SPANS[child.columnSpan] ?? '') : ''}
                >
                    {renderChildren([child])}
                </div>
            ))}
        </div>
    );
}

export default function FieldsetLayout({ node, renderChildren }: LayoutProps) {
    return (
        <fieldset className="rounded-lg border border-border p-4">
            {node.label ? (
                <legend className="px-2 text-sm font-semibold text-foreground">{node.label}</legend>
            ) : null}
            {gridChildren(node, renderChildren)}
        </fieldset>
    );
}