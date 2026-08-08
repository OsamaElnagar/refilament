<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Widgets\PieChartWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Refilament\Widgets\PostsStatusChart;
use Workbench\App\Refilament\Widgets\RecentPostsTableWidget;

final class WidgetsOverviewController
{
    /**
     * Serve a free-standing page hosting widgets (slices 3.1/3.2). An
     * app-owned standalone page, wired by hand here because the panel shell
     * that auto-registers widget-bearing pages lands with 1.9. The widgets
     * serialize self-contained nodes (`stats_overview`, `chart_bar`,
     * `chart_pie`); the React runtime renders them with no round trips.
     */
    public function __invoke(): InertiaResponse
    {
        $widgets = [
            StatsOverviewWidget::make()
                ->heading('Content overview')
                ->description('A live snapshot of the workbench database')
                ->columns(4)
                ->stats([
                    Stat::make('Total posts', fn (): int => Post::withoutGlobalScopes()->count())
                        ->icon('tag')
                        ->color('primary'),
                    Stat::make('Published posts', fn (): int => Post::query()->whereNotNull('published_at')->count())
                        ->icon('check-circle')
                        ->color('success')
                        ->description('with a published date'),
                    Stat::make('Total users', fn (): int => User::query()->count())
                        ->icon('users')
                        ->color('info'),
                ]),
            // The live-data demo (slice 3.2): a filter select + a 10s polling
            // interval, served by the typed widget data endpoint. The single
            // demo() factory is also what the widget resolver rebuilds, so the
            // snapshot and every refetch always agree.
            PostsStatusChart::demo(),
            PieChartWidget::make()
                ->heading('Posts per author')
                ->description('Share of posts written by each author')
                ->color('info')
                ->data(static function (): array {
                    $rows = Post::query()->toBase()
                        ->selectRaw('author, count(*) as total')
                        ->groupBy('author')
                        ->orderByDesc('total')
                        ->limit(4)
                        ->get();

                    return [
                        'labels' => $rows->pluck('author')->all(),
                        'datasets' => [[
                            'label' => 'Posts',
                            'data' => $rows->pluck('total')->all(),
                        ]],
                    ];
                }),
            // A widget that is itself a table (slice D1) — the Ahram
            // `RecentSalesInvoicesTable` idiom. The node embeds the table's
            // first page; sorting/pagination hit the typed table endpoint
            // (the widget's table is registered under this id).
            RecentPostsTableWidget::make()->heading('Recent posts'),
        ];

        return Inertia::render('refilament/widgets-overview', [
            'widgets' => array_map(
                static fn (object $widget): array => $widget->toArray(),
                $widgets,
            ),
        ]);
    }
}
