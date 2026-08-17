import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2 } from 'lucide-react';
import {
    Bar,
    BarChart,
    Line,
    LineChart,
    CartesianGrid,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartConfig,
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { panelUrl } from '@/lib/panel';
import { getField } from '@/schemas/registry';
import type { ChartData, ChartWidgetNode, WidgetColor } from '@/widgets/types';

/**
 * Widget color → a CSS custom property. Following slice 1.9 "->colors()
 * beyond primary", series colors read theme vars rather than hardcoded
 * values, so a panel's `colors` config (applied by AppShell as `--{key}`
 * on the shell root) drives them: configuring `success => '#16a34a'`
 * makes `var(--success)` resolve to it for every chart.
 */
const SERIES_COLORS: Record<WidgetColor, string> = {
    primary: 'var(--primary)',
    secondary: 'var(--secondary)',
    danger: 'var(--destructive)',
    success: 'var(--success)',
    warning: 'var(--warning)',
    info: 'var(--info)',
};

/**
 * Build per-dataset colors: the configured color for the first series, then
 * the theme's chart palette (`--chart-{n}`) — overridable via `colors`, same
 * as every other token.
 */
function seriesColorFor(base: string, index: number): string {
    if (index === 0) {
        return base;
    }
    return `var(--chart-${((index - 1) % 5) + 1})`;
}

/**
 * Renders a `chart_*` widget (slice 3.2) via the shadcn Chart (Recharts)
 * wrapper. By default the widget is a static server snapshot: the `data`
 * node ships the same `{ labels, datasets }` shape Filament hands Chart.js,
 * resolved server-side at serialization. When the widget declares filters
 * and/or a polling interval (slice 3.2), this component opts into the live
 * surface: a compact filter bar rendered from the filter schema's fields, and
 * a client timer that refetches the typed data endpoint — the honest
 * request/response model, with the widget rebuilt per request server-side.
 */
export default function ChartWidget({ node }: { node: ChartWidgetNode }) {
    const chartType = node.type.replace('chart_', '') as 'bar' | 'line' | 'pie' | 'doughnut';
    const baseColor = SERIES_COLORS[node.color ?? 'primary'] ?? SERIES_COLORS.primary;

    const [chartData, setChartData] = useState<ChartData>(node.data);
    const [filterValues, setFilterValues] = useState<Record<string, unknown>>(node.filters?.data ?? {});
    const [isRefreshing, setIsRefreshing] = useState(false);
    const filterValuesRef = useRef(filterValues);
    const fetchSeq = useRef(0);

    filterValuesRef.current = filterValues;

    /**
     * Refetch the widget's data endpoint with the current filter values.
     * Responses are superseded — a newer refetch drops a stale one. A failed
     * poll silently keeps the last good snapshot (no spinner-error UX).
     */
    const refetch = useCallback(
        async (filters: Record<string, unknown>): Promise<void> => {
            if (!node.id) {
                return;
            }

            const seq = ++fetchSeq.current;

            setIsRefreshing(true);

            try {
                const params = new URLSearchParams();

                for (const [name, value] of Object.entries(filters)) {
                    if (value !== undefined && value !== null && value !== '') {
                        params.append(`filter[${name}]`, String(value));
                    }
                }

                const query = params.toString();
                const response = await fetch(panelUrl(`/widget/${node.id}/data${query ? `?${query}` : ''}`), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok || seq !== fetchSeq.current) {
                    return;
                }

                const payload = (await response.json()) as { data: ChartData };

                setChartData(payload.data);
            } catch {
                // Keep the last good snapshot.
            } finally {
                if (seq === fetchSeq.current) {
                    setIsRefreshing(false);
                }
            }
        },
        [node.id],
    );

    /**
     * Polling (slice 3.2): a client timer over the typed data endpoint,
     * paused while the tab is hidden. The refetch carries the current filter
     * values via the ref, so the interval is created once per widget.
     */
    useEffect(() => {
        if (!node.pollingInterval) {
            return;
        }

        const timer = window.setInterval(() => {
            if (!document.hidden) {
                void refetch(filterValuesRef.current);
            }
        }, node.pollingInterval * 1000);

        return () => window.clearInterval(timer);
    }, [node.pollingInterval, refetch]);

    const handleFilterChange = (name: string, value: unknown): void => {
        const next = { ...filterValues, [name]: value };

        setFilterValues(next);
        void refetch(next);
    };

    const { labels, datasets } = chartData;

    // Pivot datasets into recharts rows: one object per label, with a key per
    // dataset ("Views by month" → { label, views }).
    const rows = labels.map((label, index) => {
        const row: Record<string, number | string> = { label };
        datasets.forEach((dataset) => {
            row[dataset.label ?? 'value'] = dataset.data[index];
        });
        return row;
    });

    // ChartConfig drives the legend + tooltip labels and per-dataset colors.
    const config: ChartConfig = {};
    datasets.forEach((dataset, i) => {
        const key = dataset.label ?? 'value';
        config[key] = {
            label: dataset.label ?? 'Value',
            color: dataset.color ?? seriesColorFor(baseColor, i),
        };
    });

    const isEmpty = labels.length === 0 || datasets.length === 0;

    return (
        <Card className="p-5">
            {node.heading ? (
                <CardHeader className="gap-0 px-0 pb-4">
                    <CardTitle className="text-sm font-medium text-foreground">{node.heading}</CardTitle>
                    {node.description ? (
                        <CardDescription className="mt-1">{node.description}</CardDescription>
                    ) : null}
                </CardHeader>
            ) : null}

            {node.filters ? (
                <div className="mb-4 flex flex-wrap items-end gap-3 border-b border-border pb-4">
                    {node.filters.schema.map((field) => {
                        const Field = getField(field.type);

                        if (!Field) {
                            return null;
                        }

                        return (
                            <div key={field.name} className="w-52">
                                <Field
                                    node={field}
                                    value={filterValues[field.name]}
                                    onChange={(value: unknown) => handleFilterChange(field.name, value)}
                                />
                            </div>
                        );
                    })}

                    {isRefreshing ? (
                        <Loader2
                            className="mb-2 size-4 animate-spin text-muted-foreground"
                            aria-label="Refreshing chart data"
                        />
                    ) : null}
                </div>
            ) : null}

            <CardContent className="px-0">
                {isEmpty ? (
                    <p className="text-sm text-muted-foreground">No data</p>
                ) : (
                    <ChartContainer config={config} className="max-h-[400px] w-full">
                        {chartType === 'pie' || chartType === 'doughnut' ? (
                            <PieChart data={rows}>
                                <ChartTooltip content={<ChartTooltipContent />} />
                                <Pie
                                    data={rows}
                                    dataKey={datasets[0]?.label ?? 'value'}
                                    nameKey="label"
                                    strokeWidth={2}
                                    innerRadius={chartType === 'doughnut' ? 60 : 0}
                                />
                                <ChartLegend content={<ChartLegendContent nameKey="label" />} />
                            </PieChart>
                        ) : (
                            <CartesianChart type={chartType} rows={rows} datasets={datasets} />
                        )}
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}

function CartesianChart({
    type,
    rows,
    datasets,
}: {
    type: 'bar' | 'line';
    rows: Array<Record<string, unknown>>;
    datasets: Array<{ label?: string }>;
}) {
    const keys: string[] = datasets.length > 0 ? datasets.map((d) => d.label ?? 'value') : ['value'];

    const series = type === 'bar'
        ? keys.map((key) => (
              <Bar key={key} dataKey={key} radius={4} fill={`var(--color-${key})`} />
          ))
        : keys.map((key) => (
              <Line key={key} type="monotone" dataKey={key} stroke={`var(--color-${key})`} strokeWidth={2} dot={false} />
          ));

    return (
        <ChartWrapper type={type} rows={rows}>
            <CartesianGrid vertical={false} />
            <XAxis dataKey="label" tickLine={false} tickMargin={10} axisLine={false} />
            <YAxis tickLine={false} axisLine={false} width={30} />
            <ChartTooltip content={<ChartTooltipContent />} />
            <ChartLegend content={<ChartLegendContent />} />
            {series}
        </ChartWrapper>
    );
}

function ChartWrapper({
    type,
    rows,
    children,
}: {
    type: 'bar' | 'line';
    rows: Array<Record<string, unknown>>;
    children: React.ReactNode;
}) {
    const data = rows as Array<Record<string, number | string>>;

    return type === 'bar' ? (
        <BarChart data={data}>{children}</BarChart>
    ) : (
        <LineChart data={data}>{children}</LineChart>
    );
}