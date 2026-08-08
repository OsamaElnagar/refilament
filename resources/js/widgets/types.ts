/**
 * TypeScript mirror of the widget payload contract (docs/CONTRACT.md,
 * "Widgets"). A widget is a self-contained, request-rendered display unit;
 * the React runtime renders the snapshot it serializes with no round trips —
 * except chart widgets that opt into the live-data surface (slice 3.2),
 * which refetch the typed widget data endpoint.
 */

import type { FieldNode } from '@/schemas/types';
import type { TablePayload } from '@/tables/types';

/** Stat color, mirroring the table cell / action color domain. */
export type WidgetColor = 'primary' | 'secondary' | 'danger' | 'success' | 'warning' | 'info';

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

/** A single dataset inside a chart widget (slice 3.2). */
export interface ChartDataset {
    data: Array<number | string>;
    /** Dataset label — fills the legend / tooltip series name. Omitted unless set. */
    label?: string;
    /** A per-dataset color override. Omitted unless set. */
    color?: string;
}

/**
 * The serialized chart data — the same `{ labels, datasets }` shape Filament
 * hands Chart.js, resolved server-side at serialization (slice 3.2).
 */
export interface ChartData {
    labels: string[];
    datasets: ChartDataset[];
}

/** The chart wire types we ship (slice 3.2). */
export type ChartType = 'bar' | 'line' | 'pie' | 'doughnut';

/** A chart widget's filter form (slice 3.2) — the serialized field nodes + their default values. */
export interface ChartFilters {
    schema: FieldNode[];
    data: Record<string, unknown>;
}

/** The serialized `chart_*` widget (slice 3.2). */
export interface ChartWidgetNode {
    type: `chart_${ChartType}`;
    /** Omitted unless configured. */
    heading?: string;
    /** Omitted unless configured. */
    description?: string;
    /** Omitted unless not primary. */
    color?: WidgetColor;
    /** Omitted unless 300 (the default). */
    height?: number;
    /** Raw Chart.js-style options passed through (legend, scales, ...). */
    options?: Record<string, unknown>;
    /** Omitted unless the widget overrides the default column span. */
    columnSpan?: number | string | Record<string, number | null>;
    /** Omitted unless the widget sets a column start. */
    columnStart?: number | string | Record<string, number | null>;
    /** Always emitted — empty array for an empty chart. */
    data: ChartData;
    /**
     * The typed data endpoint's `{widget}` address — emitted only when the
     * widget declares filters or a polling interval (slice 3.2).
     */
    id?: string;
    /** Refetch the data endpoint every N seconds — emitted only when set. */
    pollingInterval?: number;
    /** Filter form re-running the data closure — emitted only when set. */
    filters?: ChartFilters;
}

/** The serialized `table` widget (slice D1) — a widget that is itself a table. */
export interface TableWidgetNode {
    type: 'table';
    /** The widget key, also the typed table endpoint's address. */
    id: string;
    /** Omitted unless configured. */
    heading?: string;
    /** Omitted unless the widget overrides the default column span. */
    columnSpan?: number | string | Record<string, number | null>;
    /** Omitted unless the widget sets a column start. */
    columnStart?: number | string | Record<string, number | null>;
    /** The table's first page — TableRenderer's `initial` payload. */
    table: TablePayload;
}

export type RefilamentWidget = StatsOverviewWidgetNode | ChartWidgetNode | TableWidgetNode;