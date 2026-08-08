import AppShell from '@/components/shell/AppShell';

interface SettingsProps {
    /** The server environment the workbench is running under (getPanelViewData()). */
    environment: string;
}

/**
 * A standalone panel page (slice 1.9 "->pages([...])") served at
 * /refilament/settings. Like the dashboard, it renders inside the panel
 * shell; its single prop is computed server-side by the page class's
 * getPanelViewData().
 */
export default function Settings(props: SettingsProps) {
    return (
        <AppShell>
            <main className="mx-auto w-full max-w-3xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Settings</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        A standalone panel page from the panel's <code>pages</code> config — served by
                        the shared panel page route, props computed server-side.
                    </p>
                </header>

                <div className="rounded-lg border border-border bg-card p-5">
                    <p className="text-sm font-medium text-muted-foreground">Environment</p>
                    <p className="mt-1 text-lg font-semibold text-foreground">{props.environment}</p>
                </div>
            </main>
        </AppShell>
    );
}