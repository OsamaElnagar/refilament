<?php

declare(strict_types=1);

namespace Workbench\App\Refilament\Pages;

use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Refilament;

/**
 * A standalone panel page (slice 1.9 "->pages([...])") for the workbench —
 * a page not tied to any resource. It opts into the sidebar (via
 * shouldRegisterNavigation) and adds its own server-side prop through
 * getPanelViewData(). Served at /refilament/settings by the shared
 * PanelPageController.
 */
class SettingsPage extends Page
{
    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected static bool $shouldRegisterNavigation = true;

    public static function getSlug(): string
    {
        return 'settings';
    }

    public static function getInertiaComponent(): string
    {
        return 'refilament/settings';
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPanelViewData(Refilament $refilament): array
    {
        return [
            'environment' => app()->environment(),
        ];
    }
}
