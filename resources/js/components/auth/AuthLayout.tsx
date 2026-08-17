import { usePage } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { panelUrl } from '@/lib/panel';

interface PanelPageShape {
    refilament?: {
        panel?: {
            brandName?: string;
            brandLogo?: string;
            path?: string;
        };
    };
}

/**
 * The centered, branded shell every first-party auth page renders in — the
 * Refilament analogue of Filament's `SimplePage` layout (brand on top, one
 * card, no sidebar). The brand comes from the server-shared
 * `refilament.panel` prop, so a consumer's `->brandName()` / `->brandLogo()`
 * theme their login page automatically.
 */
export default function AuthLayout({
    title,
    description,
    children,
}: PropsWithChildren<{ title: string; description: string }>): ReactNode {
    const { refilament } = usePage().props as { refilament?: PanelPageShape['refilament'] };
    const panel = refilament?.panel;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div className="flex w-full max-w-sm flex-col gap-6">
                <a
                    href={panelUrl('')}
                    className="flex items-center justify-center gap-2 font-medium"
                >
                    {panel?.brandLogo ? (
                        <img
                            src={panel.brandLogo}
                            alt={panel?.brandName}
                            className="h-7 w-auto"
                        />
                    ) : (
                        <span className="flex h-7 w-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <span className="text-sm font-bold">
                                {(panel?.brandName ?? 'R').charAt(0)}
                            </span>
                        </span>
                    )}
                    <span>{panel?.brandName ?? 'Refilament'}</span>
                </a>

                <Card className="overflow-hidden">
                    <CardHeader className="text-center">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {description ? <CardDescription>{description}</CardDescription> : null}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
