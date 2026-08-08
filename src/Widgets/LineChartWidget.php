<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

/**
 * A line chart widget (slice 3.2 — docs/ROADMAP.md "3.2 Charts widget").
 */
class LineChartWidget extends ChartWidget
{
    public function getChartType(): string
    {
        return 'line';
    }
}
