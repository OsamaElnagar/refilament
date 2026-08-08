import { usePage } from '@inertiajs/react';
import type { CSSProperties, PropsWithChildren } from 'react';

import PanelSidebar, { type PanelConfig } from '@/components/shell/PanelSidebar';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';

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
 */
export default function AppShell({ children }: PropsWithChildren) {
    const { props } = usePage();
    const colors = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel?.colors;

    const style = colors
        ? (Object.fromEntries(
              Object.entries(colors).map(([key, value]) => [`--${key.replaceAll('_', '-')}`, value]),
          ) as CSSProperties)
        : undefined;

    return (
        <SidebarProvider>
            <div className="flex min-h-svh w-full" style={style}>
                <PanelSidebar />

                <SidebarInset>
                    <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
                        <SidebarTrigger className="-ml-1" />
                    </header>

                    <main className="flex flex-1 flex-col gap-4 p-4 pt-0 sm:p-6 sm:pt-4">
                        {children}
                    </main>
                </SidebarInset>
            </div>
        </SidebarProvider>
    );
}