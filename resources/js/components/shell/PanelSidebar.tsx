import { Link, usePage } from '@inertiajs/react';
import { BarChart3, ChevronDown, CircleDashed, Droplet, FileStack, GalleryVerticalEnd, LayoutDashboard, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

export interface PanelConfig {
    id: string;
    brandName: string;
    sidebarCollapsible: boolean;
    dashboardUrl: string;
    colors?: Record<string, string>;
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
    openInNewTab?: boolean;
}

/**
 * Resolve a server-side icon string to a Lucide icon. The PHP builder sends
 * an arbitrary string key (usually a Filament-style icon name); map the known
 * ones here and fall back to a neutral glyph so an unknown key never breaks
 * the shell.
 */
function iconFor(name?: string): LucideIcon {
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
            return CircleDashed;
    }
}

export default function PanelSidebar() {
    const { props, url } = usePage();
    const panel = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel;
    const dashboardUrl = panel?.dashboardUrl ?? '/refilament';

    return (
        <Sidebar>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="group-data-[collapsible=icon]:!p-1.5">
                            <Link href={dashboardUrl}>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                    <GalleryVerticalEnd className="size-4" />
                                </div>
                                <span className="truncate font-semibold">
                                    {panel?.brandName ?? 'Refilament'}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
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
            </SidebarContent>

            <SidebarRail />
        </Sidebar>
    );
}

/**
 * A sidebar group. Collapsible groups get a chevron that toggles their members
 * (pure client state — not a new primitive, and nothing persisted between
 * requests). Non-collapsible groups render as a plain labelled section.
 */
function PanelGroup({ group, currentUrl }: { group: PanelNavGroup; currentUrl: string }) {
    const Icon = iconFor(group.icon);
    const [open, setOpen] = useState(group.collapsed !== true);

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
                onClick={() => setOpen((value) => !value)}
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
    const active =
        item.url === '/refilament'
            ? currentUrl === item.url || currentUrl === '/refilament'
            : currentUrl.startsWith(item.url);

    return (
        <SidebarMenuButton asChild isActive={active}>
            <Link href={item.url} {...(item.openInNewTab ? { target: '_blank', rel: 'noreferrer' } : {})}>
                {Icon && <Icon className="size-4" />}
                <span>{item.label}</span>
                {item.badge !== undefined ? <Badge className="ml-auto text-xs">{item.badge}</Badge> : null}
            </Link>
        </SidebarMenuButton>
    );
}