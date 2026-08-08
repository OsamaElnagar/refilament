import type { ReactNode } from 'react';
import { toast } from 'sonner';

import { ICONS } from '@/tables/cell';

/**
 * A serialized server notification (slice 3.4; docs/CONTRACT.md,
 * "Notification"). Only `title` is guaranteed — the rest appear when the PHP
 * builder configured them (omit-when-unset).
 */
export interface NotificationPayload {
    title: string;
    body?: string;
    status?: 'success' | 'danger' | 'info' | 'warning';
    icon?: string;
    duration?: number | 'persistent';
}

/** Sonner's option shape for a single toast call. */
interface ToastOptions {
    description?: string;
    duration?: number | undefined;
    icon?: ReactNode;
}

/**
 * Map a server notification to a sonner toast (slice 3.4). `status` picks the
 * toast variant (success / error / info / warning — sonner has no "danger",
 * it maps to `error`), `title` is the headline, `body` the description,
 * `icon` a lucide glyph beside the title (resolved from the shared table icon
 * map, unknown names dropped gracefully), and `duration` sonner's timeout
 * ('persistent' → stays until dismissed). Callers fall back to a plain
 * `toast.success(message)` when no notification object is present, so the
 * pre-3.4 flat message path is untouched.
 */
export function renderNotification(notification: NotificationPayload): void {
    const options: ToastOptions = {};

    if (notification.body) {
        options.description = notification.body;
    }

    if (notification.icon) {
        const Icon = ICONS[notification.icon];

        if (Icon) {
            options.icon = <Icon className="size-4" />;
        }
    }

    if (notification.duration !== undefined) {
        options.duration = notification.duration === 'persistent' ? Infinity : notification.duration;
    }

    const variant = notification.status ?? 'success';

    switch (variant) {
        case 'danger':
            toast.error(notification.title, options);
            break;
        case 'info':
            toast.info(notification.title, options);
            break;
        case 'warning':
            toast.warning(notification.title, options);
            break;
        default:
            toast.success(notification.title, options);
    }
}