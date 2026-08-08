<?php

declare(strict_types=1);

namespace Refilament\Refilament\Panel;

use Illuminate\Support\ServiceProvider;
use Refilament\Refilament\Refilament;

/**
 * The consumer-owned panel provider (mirrors Filament's `PanelProvider`).
 *
 * The `refilament:install` command generates one concrete subclass in the
 * consumer app (app/Providers/RefilamentPanelProvider.php) and registers it
 * in bootstrap/providers.php. The consumer's `panel(Panel $panel)` receives
 * the package's config-seeded panel — resources, pages, id, path, brand,
 * colors, middleware and the auth gate already populated from
 * config/refilament.php — and chains its overrides:
 *
 *     return $panel
 *         ->path('admin')
 *         ->brandName('My App')
 *         ->colors(['primary' => '#e11d48'])
 *         ->middleware(['web'])
 *         ->authMiddleware([Authenticate::class])
 *         ->loginUrl('/login');
 *
 * Registration hands the closure to Refilament::registerPanel() (this
 * package's registerPanel), which the framework resolves when the panel is
 * first needed — routes are registered from it at boot, and the shell reads
 * it on every request. There is no Livewire component behind this; the
 * provider is pure configuration, exactly the request/response model the
 * architecture docs call for.
 */
abstract class PanelProvider extends ServiceProvider
{
    abstract public function panel(Panel $panel): Panel;

    public function register(): void
    {
        $this->app->make(Refilament::class)->registerPanel(
            fn (Panel $panel): Panel => $this->panel($panel),
        );
    }
}
