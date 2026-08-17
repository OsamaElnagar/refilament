import { Link, usePage } from '@inertiajs/react';
import { BarChart3, ChevronDown, CircleDashed, Droplet, FileStack, GalleryVerticalEnd, LayoutDashboard, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { SHELL_SLOTS, ShellSlot } from '@/components/shell/ShellSlots';
import { ICONS } from '@/components/icon';

/** Tailwind badge classes per serialized color, layered onto the outline
 * variant so the pill stays legible in the sidebar. */
const NAV_BADGE_COLORS: Record<string, string> = {
    primary: 'border-primary/30 bg-primary/10 text-primary',
    secondary: 'border-border/70 bg-muted text-muted-foreground',
    success: 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    danger: 'border-rose-600/30 bg-rose-600/10 text-rose-700 dark:text-rose-400',
    warning: 'border-amber-600/30 bg-amber-600/10 text-amber-700 dark:text-amber-400',
    info: 'border-sky-600/30 bg-sky-600/10 text-sky-700 dark:text-sky-400',
};
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { panelUrl } from '@/lib/panel';
import { cn } from '@/lib/utils';

export interface PanelConfig {
    id: string;
    brandName: string;
    /** A brand-logo URL (slice B2) rendered beside the brand name. */
    brandLogo?: string;
    sidebarCollapsible: boolean;
    /** Render the navigation in a top bar instead of the sidebar (slice B2). */
    topNavigation?: boolean;
    /** The panel's URL prefix — every panel URL is built under it. */
    path?: string;
    dashboardUrl: string;
    colors?: Record<string, string>;
    /**
     * Armed shell render hooks (slice B1): slot name => client component key.
     * The shell renders each armed slot's registered component.
     */
    renderHooks?: Record<string, string>;
    /**
     * Database-notifications bell (slice B3): present when the panel opts in,
     * with the client polling interval in Filament's '7s' / '150s' style.
     */
    notifications?: { polling?: string };
    /**
     * Account-page links for the shell's user menu (slice 1.9 "user menu") —
     * each present only when the server enabled the feature behind it, so the
     * menu renders a link exactly when the route exists. The logout URL is
     * shared whenever any auth page is mounted.
     */
    profileUrl?: string;
    twoFactorUrl?: string;
    logoutUrl?: string;
    groups: PanelNavGroup[];
    items: PanelNavItem[];
}

export interface PanelNavGroup {
    label: string;
    icon?: string;
    collapsible?: boolean;
    collapsed?: boolean;
    items: PanelNavItem[];
}

export interface PanelNavItem {
    key: string;
    label: string;
    url: string;
    icon?: string;
    badge?: string | number;
    badgeColor?: string;
    badgeTooltip?: string;
    openInNewTab?: boolean;
    /**
     * Sub-navigation (the page-clusters slice) — a cluster's sidebar entry
     * carries its members as children; the parent renders as a collapsible
     * node with a chevron.
     */
    children?: PanelNavItem[];
}

/**
 * Resolve a server-side icon string to a Lucide icon. The PHP builder sends
 * an arbitrary string key (usually a Filament-style icon name); map the known
 * ones here, then fall back to the shared cell-icon registry (cell.tsx — the
 * keys stat icons, table cells and hint actions already use), and finally to
 * a neutral glyph so an unknown key never breaks the shell.
 */
export function iconFor(name?: string): LucideIcon {
    switch (name) {
        case 'heroicon-o-droplet':
        case 'droplet':
            return Droplet;
        case 'heroicon-o-document-text':
        case 'document':
            return FileStack;
        case 'heroicon-o-table-cells':
        case 'table':
            return LayoutDashboard;
        case 'chart-bar':
            return BarChart3;
        default:
            break;
    }

    if (name && ICONS[name]) {
        return ICONS[name];
    }

    return CircleDashed;
}

export default function PanelSidebar() {
    const { props, url } = usePage();
    const panel = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel;
    const dashboardUrl = panel?.dashboardUrl ?? panelUrl('');

    return (
        <Sidebar>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            className="group-data-[collapsible=icon]:!p-1.5"
                            render={
                                <Link href={dashboardUrl}>
                                    <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                        {panel?.brandLogo ? (
                                            <img src={panel.brandLogo} alt="" className="size-full object-contain p-1" />
                                        ) : (
                                            <GalleryVerticalEnd className="size-4" />
                                        )}
                                    </div>
                                    <span className="truncate font-semibold">
                                        {panel?.brandName ?? 'Refilament'}
                                    </span>
                                </Link>
                            }
                        />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <ShellSlot name={SHELL_SLOTS.sidebarLogoAfter} />

            <SidebarContent>
                <ShellSlot name={SHELL_SLOTS.sidebarStart} />
                <ShellSlot name={SHELL_SLOTS.sidebarNavStart} />

                {panel?.groups.map((group) => (
                    <PanelGroup key={group.label} group={group} currentUrl={url} />
                ))}

                {panel?.items.length ? (
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {panel.items.map((item) => (
                                    <SidebarMenuItem key={item.key}>
                                        <PanelItemButton item={item} currentUrl={url} />
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ) : null}

                <ShellSlot name={SHELL_SLOTS.sidebarNavEnd} />
            </SidebarContent>

            <SidebarFooter>
                <ShellSlot name={SHELL_SLOTS.sidebarFooter} />
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    );
}

/**
 * Whether a collapsible group renders open. A persisted "collapsed=1" cookie
 * wins over the config-seeded default, so a user's choice survives navigation.
 * Cookie reads are best-effort (document.cookie parsing can be empty in SSR).
 */
function groupIsOpen(group: PanelNavGroup, cookieName: string): boolean {
    if (group.collapsible !== true) {
        return true;
    }

    if (typeof document === 'undefined') {
        return group.collapsed !== true;
    }

    const stored = document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(`${cookieName}=`));

    if (stored !== undefined) {
        return stored.endsWith('=1') === false;
    }

    return group.collapsed !== true;
}

/** Persist a group's open/collapsed state to a cookie (path-scoped to the panel). */
function setGroupCollapsed(cookieName: string, isOpen: boolean): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${cookieName}=${isOpen ? '0' : '1'}; path=/; SameSite=Lax; max-age=31536000`;
}

/**
 * A sidebar group. Collapsible groups get a chevron that toggles their members.
 * The collapsed state is client-owned and persisted to a cookie keyed by the
 * group's label (slice 1.9 — a group stays collapsed across panel navigation,
 * no server round trip). The `collapsed` config flag seeds the initial state;
 * a "collapsed=1" cookie overrides it, and closing a group writes the cookie.
 */
function PanelGroup({ group, currentUrl }: { group: PanelNavGroup; currentUrl: string }) {
    const Icon = iconFor(group.icon);
    const cookieName = `refilament-nav-${group.label}`;
    const [open, setOpen] = useState(() => groupIsOpen(group, cookieName));

    if (group.collapsible !== true) {
        return (
            <SidebarGroup>
                <SidebarGroupLabel>
                    {Icon && <Icon className="size-4" />}
                    {group.label}
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.key}>
                                <PanelItemButton item={item} currentUrl={currentUrl} />
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        );
    }

    return (
        <SidebarGroup>
            <button
                type="button"
                onClick={() => {
                    const next = !open;
                    setOpen(next);
                    setGroupCollapsed(cookieName, next);
                }}
                className="flex w-full items-center justify-between gap-2 px-2 py-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground"
            >
                <span className="flex min-w-0 items-center gap-2">
                    {Icon && <Icon className="size-4" />}
                    {group.label}
                </span>
                <ChevronDown className={cn('size-3 transition-transform', open ? 'rotate-180' : '')} />
            </button>
            {open ? (
                <SidebarGroupContent>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.key}>
                                <PanelItemButton item={item} currentUrl={currentUrl} />
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroupContent>
            ) : null}
        </SidebarGroup>
    );
}

function PanelItemButton({ item, currentUrl }: { item: PanelNavItem; currentUrl: string }) {
    const Icon = iconFor(item.icon);
    // The dashboard nav item (whose URL equals the panel root) is active only
    // on the dashboard itself — the derived panel root, so a consumer's
    // ->path('admin') keeps the check honest.
    const dashboardUrl = panelUrl('');
    const active =
        item.url === dashboardUrl
            ? currentUrl === item.url || currentUrl === dashboardUrl
            : currentUrl.startsWith(item.url);

    // A cluster entry (page-clusters slice): a collapsible parent node whose
    // children are the cluster's members. The parent is active when any child
    // is active; the chevron toggles the sub-navigation, open by default and
    // staying open while a child is current.
    if (item.children && item.children.length > 0) {
        return <PanelClusterItem item={item} currentUrl={currentUrl} />;
    }

    return (
        <SidebarMenuButton
            isActive={active}
            render={
                <Link href={item.url} {...(item.openInNewTab ? { target: '_blank', rel: 'noreferrer' } : {})}>
                    {Icon && <Icon className="size-4" />}
                    <span>{item.label}</span>
                    {item.badge !== undefined ? (
                        <Badge
                            variant="outline"
                            title={item.badgeTooltip}
                            className={cn(
                                'ml-auto text-xs',
                                item.badgeColor ? (NAV_BADGE_COLORS[item.badgeColor] ?? NAV_BADGE_COLORS.secondary) : '',
                            )}
                        >
                            {item.badge}
                        </Badge>
                    ) : null}
                </Link>
            }
        />
    );
}

/**
 * A cluster's sidebar entry — a collapsible parent (label + icon + chevron)
 * with its members as nested sub-navigation. Open by default; while a member
 * is current the group stays open.
 */
function PanelClusterItem({ item, currentUrl }: { item: PanelNavItem; currentUrl: string }) {
    const Icon = iconFor(item.icon);
    const childActive = (item.children ?? []).some((child) => currentUrl.startsWith(child.url));
    const [open, setOpen] = useState(() => childActive);
    const isOpen = open || childActive;

    return (
        <SidebarMenuItem>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={isOpen}
                className="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-sidebar-foreground transition hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
            >
                <span className="flex min-w-0 items-center gap-2">
                    {Icon && <Icon className="size-4" />}
                    <span className="truncate">{item.label}</span>
                </span>
                <ChevronDown className={cn('size-3 shrink-0 transition-transform', isOpen ? 'rotate-180' : '')} />
            </button>
            {isOpen ? (
                <div className="mt-0.5 space-y-0.5 border-l border-border pl-3 ml-3">
                    {(item.children ?? []).map((child) => (
                        <PanelItemButton key={child.key} item={child} currentUrl={currentUrl} />
                    ))}
                </div>
            ) : null}
        </SidebarMenuItem>
    );
}