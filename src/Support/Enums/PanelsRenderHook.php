<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Enums;

/**
 * The shell's render-hook slots (slice B1, expanded). Each case mirrors the
 * corresponding Filament `PanelsRenderHook::*` constant's value, so a slot
 * name means the same thing here as it does upstream. A slot only renders
 * when the server arms it (`Panel::renderHook()`) AND the app registered a
 * component for it (`registerShellSlot`) — declaring the hook is what arms
 * it, exactly like Filament.
 *
 * Only the slots our React shell can actually place are enumerated here.
 * Filament also exposes blade-only slots (auth forms, <head>/<body>,
 * scripts/styles, simple-layout, resource/table internals) that have no React
 * equivalent; those are intentionally deferred.
 */
enum PanelsRenderHook: string
{
    case SidebarFooter = 'panels::sidebar.footer';

    case SidebarLogoAfter = 'panels::sidebar.logo.after';

    case SidebarStart = 'panels::sidebar.start';

    case SidebarNavStart = 'panels::sidebar.nav.start';

    case SidebarNavEnd = 'panels::sidebar.nav.end';

    case TopbarBefore = 'panels::topbar.before';

    case TopbarStart = 'panels::topbar.start';

    case TopbarEnd = 'panels::topbar.end';

    case TopbarAfter = 'panels::topbar.after';

    case LayoutStart = 'panels::layout.start';

    case LayoutEnd = 'panels::layout.end';

    case ContentBefore = 'panels::content.before';

    case ContentStart = 'panels::content.start';

    case ContentEnd = 'panels::content.end';

    case ContentAfter = 'panels::content.after';

    case PageStart = 'panels::page.start';

    case PageEnd = 'panels::page.end';

    case Footer = 'panels::footer';

    case UserMenuBefore = 'panels::user-menu.before';

    case UserMenuAfter = 'panels::user-menu.after';

    case GlobalSearchStart = 'panels::global-search.start';

    case GlobalSearchEnd = 'panels::global-search.end';
}
