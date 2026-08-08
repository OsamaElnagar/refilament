<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tests\Fixtures;

use Refilament\Refilament\Pages\Page;

/**
 * A custom resource page that does NOT opt into the panel sidebar — proves the
 * panel skips pages whose shouldRegisterNavigation() is false.
 */
final class NonCustomStatsPage extends Page
{
    protected static string $routePath = '/stats';

    public static function getInertiaComponent(): string
    {
        return 'refilament/post-stats';
    }
}
