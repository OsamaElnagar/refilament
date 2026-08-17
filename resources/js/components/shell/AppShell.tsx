import { Link, usePage } from '@inertiajs/react';
import type { CSSProperties, PropsWithChildren } from 'react';
import { GalleryVerticalEnd } from 'lucide-react';

import GlobalSearch from '@/components/search/GlobalSearch';
import NotificationsBell from '@/components/shell/NotificationsBell';
import PanelSidebar, { iconFor, type PanelConfig } from '@/components/shell/PanelSidebar';
import { SHELL_SLOTS, ShellSlot } from '@/components/shell/ShellSlots';
import UserMenu from '@/components/shell/UserMenu';
import ThemeToggle from '@/components/theme/ThemeToggle';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { panelUrl } from '@/lib/panel';
import { cn } from '@/lib/utils';

/**
 * The panel shell (slice 1.9 — docs/ROADMAP.md "1.9 Panel shell"). Composes
 * the shadcn SidebarProvider around the sidebar and the active page: an Inset
 * column whose header carries the collapse Trigger, with the page rendered
 * underneath. Pages mount this as their layout; the nav data comes from the
 * server-shared `props.refilament.panel` read by PanelSidebar.
 *
 * The panel's `colors` become CSS custom properties applied on the shell root
 * (key `primary` → `--primary`), so the theme's primary utilities (`bg-primary`
 * etc. resolve to `var(--primary)`) pick up the configured brand color.
 *
 * Slice B2 — `topNavigation()`: when the panel opts in, the sidebar is
 * replaced by a horizontal top bar carrying the brand + the same nav items
 * (groups render as inline section labels), mirroring Filament's top-nav mode.
 * Both variants read the identical `props.refilament.panel` contract.
 */
export default function AppShell({ children }: PropsWithChildren) {
    const { props, url } = usePage();
    const panel = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel;
    const colors = panel?.colors;

    const style = colors
        ? (Object.fromEntries(
              Object.entries(colors).map(([key, value]) => [`--${key.replaceAll('_', '-')}`, value]),
          ) as CSSProperties)
        : undefined;

    if (panel?.topNavigation) {
        return (
            <>
                <ShellSlot name={SHELL_SLOTS.layoutStart} />
                <div className="flex min-h-svh w-full flex-col" style={style}>
                    <TopNav panel={panel} currentUrl={url} />

                    <ShellSlot name={SHELL_SLOTS.contentBefore} />
                    <main className="flex flex-1 flex-col gap-4 p-4 pt-6 sm:p-6">
                        <ShellSlot name={SHELL_SLOTS.contentStart} />
                        <ShellSlot name={SHELL_SLOTS.pageStart} />
                        {children}
                        <ShellSlot name={SHELL_SLOTS.pageEnd} />
                        <ShellSlot name={SHELL_SLOTS.contentEnd} />
                        <ShellSlot name={SHELL_SLOTS.footer} />
                    </main>
                    <ShellSlot name={SHELL_SLOTS.contentAfter} />
                </div>
                <ShellSlot name={SHELL_SLOTS.layoutEnd} />
            </>
        );
    }

    return (
        <SidebarProvider>
            <ShellSlot name={SHELL_SLOTS.layoutStart} />
            <div className="flex min-h-svh w-full" style={style}>
                <PanelSidebar />

                <SidebarInset>
                    <ShellSlot name={SHELL_SLOTS.topbarBefore} />
                    <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
                        <ShellSlot name={SHELL_SLOTS.topbarStart} />
                        <SidebarTrigger className="-ml-1" />
                        <div className="ml-auto flex items-center gap-1">
                            <GlobalSearch />
                            <ThemeToggle />
                            <NotificationsBell />
                            <UserMenu />
                            <ShellSlot name={SHELL_SLOTS.topbarEnd} />
                        </div>
                    </header>
                    <ShellSlot name={SHELL_SLOTS.topbarAfter} />

                    <ShellSlot name={SHELL_SLOTS.contentBefore} />
                    <main className="flex flex-1 flex-col gap-4 p-4 pt-0 sm:p-6 sm:pt-4">
                        <ShellSlot name={SHELL_SLOTS.contentStart} />
                        <ShellSlot name={SHELL_SLOTS.pageStart} />
                        {children}
                        <ShellSlot name={SHELL_SLOTS.pageEnd} />
                        <ShellSlot name={SHELL_SLOTS.contentEnd} />
                        <ShellSlot name={SHELL_SLOTS.footer} />
                    </main>
                    <ShellSlot name={SHELL_SLOTS.contentAfter} />
                </SidebarInset>
            </div>
            <ShellSlot name={SHELL_SLOTS.layoutEnd} />
        </SidebarProvider>
    );
}

/**
 * The top-navigation variant (slice B2): a horizontal bar with the brand on
 * the left, the same nav items the sidebar renders (grouped items carry their
 * group label as an inline section heading), and the search + theme controls
 * on the right. Active state is derived from the current URL exactly as the
 * sidebar does.
 */
function TopNav({ panel, currentUrl }: { panel: PanelConfig; currentUrl: string }) {
    return (
        <header className="sticky top-0 z-40 flex h-14 shrink-0 items-center gap-4 border-b bg-background px-4">
            <ShellSlot name={SHELL_SLOTS.topbarStart} />
            <Link href={panel.dashboardUrl} className="flex items-center gap-2">
                <div className="flex aspect-square size-7 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                    {panel.brandLogo ? (
                        <img src={panel.brandLogo} alt="" className="size-full object-contain p-0.5" />
                    ) : (
                        <GalleryVerticalEnd className="size-4" />
                    )}
                </div>
                <span className="truncate font-semibold">{panel.brandName}</span>
            </Link>

            <nav className="ml-2 flex min-w-0 flex-1 items-center gap-1 overflow-x-auto">
                {panel.groups.map((group) => (
                    <span key={group.label} className="flex shrink-0 items-center gap-1">
                        <span className="px-2 text-xs font-semibold text-muted-foreground">{group.label}</span>
                        {group.items.map((item) => (
                            <TopNavLink key={item.key} item={item} currentUrl={currentUrl} />
                        ))}
                    </span>
                ))}
                {panel.items.map((item) => (
                    <TopNavLink key={item.key} item={item} currentUrl={currentUrl} />
                ))}
            </nav>

            <div className="ml-auto flex shrink-0 items-center gap-1">
                <GlobalSearch />
                <ThemeToggle />
                <NotificationsBell />
                <UserMenu />
                <ShellSlot name={SHELL_SLOTS.topbarEnd} />
            </div>
        </header>
    );
}

function TopNavLink({ item, currentUrl }: { item: PanelConfig['items'][number]; currentUrl: string }) {
    const Icon = iconFor(item.icon);
    // The dashboard nav item is active only on the dashboard itself — compare
    // against the derived panel root so a consumer's ->path('admin') keeps
    // the check honest.
    const dashboardUrl = panelUrl('');
    const active =
        item.url === dashboardUrl
            ? currentUrl === item.url || currentUrl === dashboardUrl
            : currentUrl.startsWith(item.url);

    return (
        <Link
            href={item.url}
            {...(item.openInNewTab ? { target: '_blank', rel: 'noreferrer' } : {})}
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition hover:bg-muted hover:text-foreground',
                active ? 'bg-muted text-foreground' : 'text-muted-foreground',
            )}
        >
            {Icon && <Icon className="size-4" />}
            <span>{item.label}</span>
        </Link>
    );
}
