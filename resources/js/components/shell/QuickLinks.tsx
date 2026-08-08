import { Link } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';

import { panelUrl } from '@/lib/panel';

/**
 * Demo shell render hook (slice B1) — registered for the 'sidebar-footer'
 * slot, mirroring Heaven's quick-links partial injected into the shell. The
 * workbench panel arms the hook server-side (`renderHook('sidebar-footer',
 * 'quick-links')`); this component supplies the UI the slot renders.
 */
export default function QuickLinks(): React.JSX.Element {
    return (
        <div className="flex items-center justify-between gap-2 px-2 py-1">
            <span className="text-xs text-muted-foreground">Refilament v0</span>

            <Link
                href={panelUrl('/playground')}
                className="inline-flex items-center gap-1 text-xs text-muted-foreground transition hover:text-foreground"
            >
                <LifeBuoy className="size-3.5" aria-hidden="true" />
                Playground
            </Link>
        </div>
    );
}
