import TwoFactorSection from '@/components/auth/TwoFactorSection';
import AppShell from '@/components/shell/AppShell';
import { Separator } from '@/components/ui/separator';

interface TwoFactorSettingsProps {
    enabled: boolean;
    enabling: boolean;
}

/**
 * The panel's standalone two-factor authentication management page — delegates
 * to the shared TwoFactorSection component for the actual enable/disable/QR/
 * recovery-codes UI, wrapped in the panel shell with a "Security" header.
 */
export default function TwoFactorSettings({ enabled, enabling }: TwoFactorSettingsProps) {
    return (
        <AppShell>
            <main className="mx-auto w-full max-w-3xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">Security</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Manage your account&apos;s two-factor authentication settings.
                    </p>
                </header>
                <Separator className="mb-8" />
                <TwoFactorSection enabled={enabled} enabling={enabling} />
            </main>
        </AppShell>
    );
}