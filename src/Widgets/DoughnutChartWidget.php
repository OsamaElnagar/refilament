<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets;

/**
 * A doughnut chart widget (slice 3.2 — docs/ROADMAP.md "3.2 Charts widget").
 */
class DoughnutChartWidget extends ChartWidget
{
    public function getChartType(): string
    {
        return 'doughnut';
    }
}
