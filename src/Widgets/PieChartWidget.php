<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

/**
 * A pie chart widget (slice 3.2 — docs/ROADMAP.md "3.2 Charts widget").
 */
class PieChartWidget extends ChartWidget
{
    public function getChartType(): string
    {
        return 'pie';
    }
}
