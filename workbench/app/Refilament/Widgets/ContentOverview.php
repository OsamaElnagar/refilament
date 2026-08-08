<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Widgets;

use Refilament\Refilament\Widgets\StatsOverviewWidget;
use Refilament\Refilament\Widgets\StatsOverviewWidget\Stat;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * The panel dashboard's content-overview widget (slice 1.9 "Dashboard"). A
 * stats overview whose cards are defined self-contained via an overridden
 * make() — the dashboard controller builds each registered widget class with
 * ::make() and serializes it, so the stat closures resolve server-side at
 * request time and never cross the wire.
 */
final class ContentOverview extends StatsOverviewWidget
{
    public static function make(): static
    {
        return parent::make()
            ->heading('Content overview')
            ->description('A snapshot of the workbench database')
            ->columns(4)
            ->stats([
                Stat::make('Total posts', fn (): int => Post::withoutGlobalScopes()->count())
                    ->icon('tag')
                    ->color('primary'),
                Stat::make('Published', fn (): int => Post::query()->whereNotNull('published_at')->count())
                    ->icon('check-circle')
                    ->color('success'),
                Stat::make('Drafts', fn (): int => Post::query()->whereNull('published_at')->count())
                    ->icon('pencil')
                    ->color('warning'),
                Stat::make('Total users', fn (): int => User::query()->count())
                    ->icon('users')
                    ->color('info'),
            ]);
    }
}
