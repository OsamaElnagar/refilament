import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Bell, CheckCheck, Loader2 } from 'lucide-react';

import type { PanelConfig } from '@/components/shell/PanelSidebar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import { cn } from '@/lib/utils';

/**
 * The database-notifications bell (slice B3) — mirrors Filament's
 * databaseNotifications(): a topbar bell showing the unread count, with a
 * dropdown listing the latest rows. The panel arms it server-side
 * (`Panel::databaseNotifications()` + an optional polling interval); the
 * component polls the typed notifications endpoint on that interval (the
 * request/response counterpart to Livewire's polling — the server never
 * remembers anything between requests), and dismisses notifications through
 * the mark-read endpoints as the user clicks them.
 */
interface NotificationItem {
    id: string;
    title: string;
    body?: string;
    url?: string;
    readAt?: string;
    createdAt?: string;
}

function pollingMs(interval?: string): number | null {
    if (!interval) {
        return null;
    }

    const seconds = Number.parseInt(interval.replace(/\D/g, ''), 10);

    return Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : null;
}

function relativeTime(iso?: string): string {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const minutes = Math.max(1, Math.round((Date.now() - then) / 60_000));

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    return `${Math.round(hours / 24)}d ago`;
}

function postHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    };

    const csrfToken = readCsrfToken();

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return headers;
}

export default function NotificationsBell(): React.JSX.Element | null {
    const { props } = usePage();
    const panel = (props as { refilament?: { panel?: PanelConfig } }).refilament?.panel;
    const notifications = panel?.notifications;

    const [items, setItems] = useState<NotificationItem[]>([]);
    const [unread, setUnread] = useState(0);
    const [running, setRunning] = useState<string | null>(null);
    const [clearing, setClearing] = useState(false);

    useEffect(() => {
        if (!notifications) {
            return;
        }

        let cancelled = false;

        const refresh = async (): Promise<void> => {
            try {
                const response = await fetch(panelUrl('/notifications'));

                if (!response.ok || cancelled) {
                    return;
                }

                const payload = (await response.json()) as { unread?: number; notifications?: NotificationItem[] };

                setItems(payload.notifications ?? []);
                setUnread(payload.unread ?? 0);
            } catch {
                // Network hiccup — the next poll retries.
            }
        };

        void refresh();

        const interval = pollingMs(notifications.polling);
        const timer = interval !== null ? window.setInterval(() => void refresh(), interval) : null;

        return () => {
            cancelled = true;

            if (timer !== null) {
                window.clearInterval(timer);
            }
        };
    }, [notifications]);

    if (!notifications) {
        return null;
    }

    const markRead = async (item: NotificationItem): Promise<void> => {
        if (item.url) {
            // Navigating away — optimistically mark read so the badge doesn't
            // bounce back on the next poll, then follow the link.
            setUnread((current) => Math.max(current - 1, 0));
            setItems((current) => current.map((entry) => (entry.id === item.id ? { ...entry, readAt: new Date().toISOString() } : entry)));
            router.visit(item.url);

            return;
        }

        if (item.readAt) {
            return;
        }

        setRunning(item.id);

        try {
            const response = await fetch(panelUrl(`/notifications/${item.id}/read`), {
                method: 'POST',
                headers: postHeaders(),
            });

            if (response.ok) {
                setUnread((current) => Math.max(current - 1, 0));
                setItems((current) => current.map((entry) => (entry.id === item.id ? { ...entry, readAt: new Date().toISOString() } : entry)));
            }
        } finally {
            setRunning(null);
        }
    };

    const markAllRead = async (): Promise<void> => {
        setClearing(true);

        try {
            const response = await fetch(panelUrl('/notifications/read-all'), {
                method: 'POST',
                headers: postHeaders(),
            });

            if (response.ok) {
                setUnread(0);
                setItems((current) => current.map((entry) => ({ ...entry, readAt: entry.readAt ?? new Date().toISOString() })));
            }
        } finally {
            setClearing(false);
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button variant="ghost" size="icon" className="relative size-9" aria-label={`${unread} unread notifications`}>
                        <Bell className="size-4" />

                        {unread > 0 ? (
                            <span className="absolute top-1.5 right-1.5 flex h-2 min-w-2 items-center justify-center rounded-full bg-destructive px-0.5">
                                <span className="sr-only">{unread}</span>
                            </span>
                        ) : null}
                    </Button>
                }
            />

            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="flex items-center justify-between">
                        <span>Notifications</span>

                        {unread > 0 ? (
                            <button
                                type="button"
                                onClick={() => void markAllRead()}
                                disabled={clearing}
                                className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground transition hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                            >
                                {clearing ? <Loader2 className="size-3 animate-spin" aria-hidden="true" /> : <CheckCheck className="size-3" aria-hidden="true" />}
                                Mark all read
                            </button>
                        ) : null}
                    </DropdownMenuLabel>

                    <DropdownMenuSeparator />

                    {items.length === 0 ? (
                        <div className="px-3 py-8 text-center text-sm text-muted-foreground">You're all caught up.</div>
                    ) : (
                        <div className="max-h-80 overflow-auto">
                            {items.map((item) => (
                                <DropdownMenuItem
                                    key={item.id}
                                    onClick={() => void markRead(item)}
                                    disabled={running === item.id}
                                    className={cn('flex-col items-start gap-0.5 py-2.5', !item.readAt && 'pr-8')}
                                >
                                    <span className="flex w-full items-center gap-2 text-sm font-medium">
                                        {!item.readAt ? <span className="size-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true" /> : null}
                                        {item.title}
                                    </span>

                                    {item.body ? <span className="text-xs text-muted-foreground">{item.body}</span> : null}

                                    {item.createdAt ? (
                                        <span className="text-xs text-muted-foreground/70">{relativeTime(item.createdAt)}</span>
                                    ) : null}
                                </DropdownMenuItem>
                            ))}
                        </div>
                    )}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
