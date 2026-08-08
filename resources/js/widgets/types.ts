/**
 * TypeScript mirror of the widget payload contract (docs/CONTRACT.md,
 * "Widgets"). A widget is a self-contained, request-rendered display unit;
 * the React runtime renders the snapshot it serializes with no round trips.
 */

/** Stat color, mirroring the table cell / action color domain. */
type WidgetColor = 'primary' | 'secondary' | 'danger' | 'success' | 'warning' | 'info';

/** A stat card inside a StatsOverviewWidget (slice 3.1). */
export interface WidgetStat {
    label: string;
    /** The display value, resolved server-side to a scalar at serialization. */
    value: string | number | null;
    /** Omitted unless configured. */
    description?: string;
    /** Omitted unless configured. */
    icon?: string;
    /** Omitted unless configured. */
    color?: WidgetColor;
}

/** The serialized `stats_overview` widget (slice 3.1). */
export interface StatsOverviewWidgetNode {
    type: 'stats_overview';
    /** Omitted unless configured. */
    heading?: string;
    /** Omitted unless configured. */
    description?: string;
    /** Omitted unless 2 (the default). */
    columns?: number;
    /** Omitted unless the widget overrides the default column span. */
    columnSpan?: number | string | Record<string, number | null>;
    /** Omitted unless the widget sets a column start. */
    columnStart?: number | string | Record<string, number | null>;
    stats: WidgetStat[];
}

export type RefilamentWidget = StatsOverviewWidgetNode;