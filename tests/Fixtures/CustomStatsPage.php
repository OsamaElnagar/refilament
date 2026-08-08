<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Pages\Page;

/**
 * A custom resource page that opts into the panel sidebar (slice 1.9) — used
 * to prove the panel emits one nav item per opt-in custom page.
 */
final class CustomStatsPage extends Page
{
    protected static string $routePath = '/stats';

    protected static ?string $navigationLabel = 'Stats';

    protected static ?string $navigationIcon = 'chart-bar';

    protected static ?int $navigationSort = -1;

    protected static bool $shouldRegisterNavigation = true;

    public static function getInertiaComponent(): string
    {
        return 'refilament/post-stats';
    }
}
