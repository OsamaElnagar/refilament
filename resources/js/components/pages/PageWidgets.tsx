import type { CSSProperties } from 'react';

import { cn } from '@/lib/utils';
import { WidgetRenderer } from '@/widgets/WidgetRenderer';
import type { RefilamentWidget } from '@/widgets/types';

interface PageWidgetsProps {
    /** Serialized widget nodes (slice 1.10) — omitted when the page declares none. */
    widgets?: RefilamentWidget[];
    /** The grid column count (header widgets default 2, footer 1). */
    columns?: number;
    className?: string;
}

/** A widget's columnSpan (a number) spans that many grid cells. */
function spanStyle(columnSpan?: RefilamentWidget['columnSpan']): CSSProperties | undefined {
    if (typeof columnSpan === 'number' && columnSpan > 1) {
        return { gridColumn: `span ${columnSpan}` };
    }

    return undefined;
}

/**
 * Renders a page's header or footer widgets (slice 1.10) in a grid above or
 * below the page's content slot — the React analogue of Filament's
 * getHeaderWidgets()/getFooterWidgets(). Each widget is a static snapshot
 * node rendered by the shared WidgetRenderer; nothing polls or refetches.
 * The column count comes from the page (headerWidgetsColumns /
 * footerWidgetsColumns), and a widget's own columnSpan widens its cell.
 */
export default function PageWidgets({ widgets, columns = 2, className }: PageWidgetsProps): React.JSX.Element | null {
    if (!widgets || widgets.length === 0) {
        return null;
    }

    const cols = Math.min(Math.max(columns, 1), 4);

    return (
        <div
            className={cn('grid gap-4', className)}
            style={{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }}
        >
            {widgets.map((widget, index) => (
                <div key={`${widget.type}-${index}`} style={spanStyle(widget.columnSpan)}>
                    <WidgetRenderer node={widget} />
                </div>
            ))}
        </div>
    );
}
