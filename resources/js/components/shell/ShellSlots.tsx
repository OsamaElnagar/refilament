import { usePage } from '@inertiajs/react';
import type { ComponentType } from 'react';

import type { PanelConfig } from '@/components/shell/PanelSidebar';

/**
 * Shell render-hook slots (slice B1) — the React counterpart to Filament's
 * render hooks. The server declares which slots are armed
 * (`panel.renderHooks`, via `Panel::renderHook()`), the app maps a declared
 * slot to a real component with `registerShellSlot`, and the shell places
 * `<ShellSlot name="..." />` at each fixed extension point (sidebar footer,
 * top-bar end, page start). A slot only renders when the server declared it
 * AND the app registered a component for it — declaring the hook is what
 * arms it, exactly like Filament.
 */
type SlotComponent = ComponentType;

const slots = new Map<string, SlotComponent>();

export function registerShellSlot(name: string, component: SlotComponent): void {
    slots.set(name, component);
}

export function ShellSlot({ name }: { name: string }): React.JSX.Element | null {
    const { props } = usePage();
    const panel = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel;
    const hooks = panel?.renderHooks;

    if (!hooks || !(name in hooks)) {
        return null;
    }

    const Component = slots.get(name);

    return Component ? <Component /> : null;
}
