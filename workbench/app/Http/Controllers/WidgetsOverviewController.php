<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Widgets\StatsOverviewWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

final class WidgetsOverviewController
{
    /**
     * Serve a free-standing page hosting a StatsOverviewWidget (slice 3.1).
     * An app-owned standalone page, wired by hand here because the panel
     * shell that auto-registers widget-bearing pages lands with 1.9. The
     * widget serializes a self-contained `stats_overview` node; the React
     * runtime renders it with no round trips.
     */
    public function __invoke(): InertiaResponse
    {
        $widget = StatsOverviewWidget::make()
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
            ]);

        return Inertia::render('refilament/widgets-overview', [
            'widget' => $widget->toArray(),
        ]);
    }
}
