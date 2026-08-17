import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { registerDefaultEntries } from '@/infolists/entries';
import { registerDefaultFields } from '@/schemas/fields';
import { registerDefaultLayouts } from '@/schemas/layouts';
import type { SchemaDocument } from '@/schemas/types';
import { registerViewComponent } from '@/schemas/views';
import PlaygroundCallout from '@/schemas/views/playground-callout';

registerDefaultFields();
registerDefaultLayouts();
registerDefaultEntries();

// Demo embedded-view component (the embedded-React slice) — the playground's
// `View::make('playground-callout')` node renders it with server-computed
// viewData as props. A consumer registers their own components here.
registerViewComponent('playground-callout', PlaygroundCallout);

const pages = import.meta.glob<{ default: ComponentType<SchemaDocument> }>(
    './pages/**/*.tsx',
    { eager: true },
);

createInertiaApp({
    title: (title) => (title ? `${title} · Refilament` : 'Refilament'),
    resolve: (name) => pages[`./pages/${name}.tsx`],
    setup({ el, App, props }) {
        createRoot(el).render(
            <TooltipProvider delay={0}>
                <App {...props} />
                <Toaster />
            </TooltipProvider>,
        );
    },
    progress: {
        color: '#6366f1',
    },
});

// This will set light / dark mode on load, mirroring the official starter kit.
initializeTheme();
