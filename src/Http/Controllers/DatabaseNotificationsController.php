<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Refilament\Refilament\Refilament;

/**
 * Serve the panel's database notifications (slice B3 — docs/CONTRACT.md,
 * "Notifications"). Mirrors Filament's databaseNotifications(): a bell in
 * the shell polls the index endpoint for the unread count and the latest
 * rows, and marks notifications read as the user dismisses them. The honest
 * request/response model — nothing here remembers state between requests;
 * the bell's polling interval is just a client timer. Notifications resolve
 * through the panel's auth guard; an unauthenticated visitor (the open
 * workbench) gets an empty payload, never an error.
 */
class DatabaseNotificationsController
{
    public function index(Request $request, Refilament $refilament): JsonResponse
    {
        return response()->json($this->payload($request, $refilament));
    }

    public function markRead(Request $request, Refilament $refilament, string $notification): JsonResponse
    {
        $user = $request->user($refilament->panel()->getAuthGuard());

        if ($user !== null) {
            $user->notifications()->whereKey($notification)->get()->each->markAsRead();
        }

        return response()->json($this->payload($request, $refilament));
    }

    public function markAllRead(Request $request, Refilament $refilament): JsonResponse
    {
        $user = $request->user($refilament->panel()->getAuthGuard());

        if ($user !== null) {
            $user->unreadNotifications()->get()->each->markAsRead();
        }

        return response()->json($this->payload($request, $refilament));
    }

    /**
     * @return array{unread: int, notifications: array<int, array<string, mixed>>}
     */
    private function payload(Request $request, Refilament $refilament): array
    {
        $user = $request->user($refilament->panel()->getAuthGuard());

        if ($user === null) {
            return ['unread' => 0, 'notifications' => []];
        }

        $notifications = $user->notifications()->latest()->limit(10)->get();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(
                fn (DatabaseNotification $notification): array => [
                    'id' => (string) $notification->getKey(),
                    'title' => (string) ($notification->data['title'] ?? 'Notification'),
                    'body' => isset($notification->data['body']) ? (string) $notification->data['body'] : null,
                    'url' => isset($notification->data['url']) ? (string) $notification->data['url'] : null,
                    // read_at / created_at are database columns (cast to
                    // Carbon by the model) — read them through getAttribute
                    // so larastan sees them, then format when present.
                    'readAt' => $this->formatTimestamp($notification->getAttribute('read_at')),
                    'createdAt' => $this->formatTimestamp($notification->getAttribute('created_at')),
                ],
            )->all(),
        ];
    }

    private function formatTimestamp(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toIso8601String() : null;
    }
}
