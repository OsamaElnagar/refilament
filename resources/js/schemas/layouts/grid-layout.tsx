import type { LayoutProps } from '@/schemas/registry';

// Tailwind v4 scans source files for literal class names, so the dynamic
// `grid-cols-N` / `col-span-N` values must live in lookup tables rather than
// string templates. Tailwind's default grid is 12 columns, so the full
// 1..12 domain is supported and matches the PHP-side clamp.
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

export default function GridLayout({ node, renderChildren }: LayoutProps) {
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
