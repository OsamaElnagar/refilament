import { usePage } from '@inertiajs/react';

import type { PanelConfig } from '@/components/shell/PanelSidebar';

/**
 * Shell render-hook slots (slice B1, every layout position) — the React
 * counterpart to Filament's render hooks. The server declares which slots are
 * armed and ships their final HTML (`panel.renderHooks`, via
 * `Panel::renderHook(...)` with a `PanelsRenderHook` case), and the shell
 * places `<ShellSlot name="..." />` at each fixed extension point (sidebar,
 * topbar, page/content, footer, user menu, global search). The slot injects
 * the armed hook's HTML directly (exactly Filament's model — a consumer
 * provides their own Blade/HTML, no separate JS bundle). A slot only renders
 * when the server declared it.
 *
 * The canonical slot values mirror Filament's `PanelsRenderHook::*` constants.
 */
export const SHELL_SLOTS = {
    sidebarFooter: 'panels::sidebar.footer',
    sidebarLogoAfter: 'panels::sidebar.logo.after',
    sidebarStart: 'panels::sidebar.start',
    sidebarNavStart: 'panels::sidebar.nav.start',
    sidebarNavEnd: 'panels::sidebar.nav.end',
    topbarBefore: 'panels::topbar.before',
    topbarStart: 'panels::topbar.start',
    topbarEnd: 'panels::topbar.end',
    topbarAfter: 'panels::topbar.after',
    layoutStart: 'panels::layout.start',
    layoutEnd: 'panels::layout.end',
    contentBefore: 'panels::content.before',
    contentStart: 'panels::content.start',
    contentEnd: 'panels::content.end',
    contentAfter: 'panels::content.after',
    pageStart: 'panels::page.start',
    pageEnd: 'panels::page.end',
    footer: 'panels::footer',
    userMenuBefore: 'panels::user-menu.before',
    userMenuAfter: 'panels::user-menu.after',
    globalSearchStart: 'panels::global-search.start',
    globalSearchEnd: 'panels::global-search.end',
} as const;

export function ShellSlot({ name }: { name: string }): React.JSX.Element | null {
    const { props } = usePage();
    const panel = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel;
    const html = panel?.renderHooks?.[name];

    if (!html) {
        return null;
    }

    return <div dangerouslySetInnerHTML={{ __html: html }} />;
}
