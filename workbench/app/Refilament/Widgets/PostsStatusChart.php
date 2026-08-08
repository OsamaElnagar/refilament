<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Widgets;

use Refilament\Refilament\Schemas\Components\Select;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Widgets\BarChartWidget;
use Workbench\App\Models\Post;

/**
 * The workbench's "posts by status" bar chart (slice 3.2 demo). Demonstrates
 * the live-data surface: a `range` filter re-runs the data closure through
 * the typed widget data endpoint (`GET /refilament/widget/posts-status-chart/data`),
 * and a polling interval refetches it. The single `demo()` factory is shared
 * by the widgets-overview page and the widget resolver, so both always agree.
 */
class PostsStatusChart extends BarChartWidget
{
    public static function demo(): static
    {
        return static::make()
            ->heading('Posts by status')
            ->description('How the posts break down across their statuses (filtered by publish date)')
            ->color('primary')
            ->pollingInterval(10)
            ->filters(Schema::make()->components([
                Select::make('range')
                    ->label('Published within')
                    ->options([
                        'all' => 'Anytime',
                        '7' => 'Last 7 days',
                        '30' => 'Last 30 days',
                    ])
                    ->default('all'),
            ]))
            ->data(static function (array $filterData): array {
                // The filter values reach the closure per request; 'all' (and
                // an absent filter) means no narrowing, so the unfiltered
                // dashboard snapshot matches the unfiltered endpoint response.
                $range = (int) ($filterData['range'] ?? 'all');

                $query = Post::query()->toBase();

                if ($range > 0) {
                    $query->where('published_at', '>=', now()->subDays($range));
                }

                $labels = ['draft', 'published', 'archived'];

                $totals = $query
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->get()
                    ->pluck('total', 'status');

                return [
                    'labels' => ['Draft', 'Published', 'Archived'],
                    'datasets' => [[
                        'label' => 'Posts',
                        'data' => array_map(static fn (string $status): int => (int) ($totals[$status] ?? 0), $labels),
                    ]],
                ];
            });
    }
}
