<?php

declare(strict_types=1);

namespace Refilament\Refilament\Notifications;

/**
 * A server-side toast notification (slice 3.4 — docs/ROADMAP.md "3.4
 * Notifications polish").
 *
 * Mirrors Filament's Notification surface (filament-source/notifications —
 * `Notification::make()->title()->body()->status()` and the Concerns traits)
 * as a pure value object: every field is plain data serialized into the typed
 * endpoint's success payload, where the React runtime renders it as a sonner
 * toast. Only the fields that are meaningful on the Filament contract are
 * carried here — `actions`/`send()`/`broadcast()` (Livewire- and
 * notification-channel-coupled) are deliberately deferred, and everything
 * remains a one-way request/response (docs/ARCHITECTURE.md — no fake
 * Livewire). Unset fields are omitted from the payload (docs/CONTRACT.md,
 * "Omission convention").
 */
class Notification
{
    protected ?string $title = null;

    protected ?string $body = null;

    /**
     * One of: success, danger, info, warning. Drives the sonner toast variant
     * and, when no explicit icon is set, the default status icon.
     */
    protected ?string $status = null;

    protected ?string $icon = null;

    /** Milliseconds, or 'persistent' for a toast that stays until dismissed. */
    protected int|string|null $duration = null;

    public static function make(): static
    {
        // @phpstan-ignore new.static (fluent factory, mirrors Filament's make())
        return new static;
    }

    /**
     * The notification title — the primary toast line.
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Optional secondary line rendered under the title.
     */
    public function body(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * The toast status: success, danger, info or warning.
     */
    public function status(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function success(): static
    {
        return $this->status('success');
    }

    public function danger(): static
    {
        return $this->status('danger');
    }

    public function info(): static
    {
        return $this->status('info');
    }

    public function warning(): static
    {
        return $this->status('warning');
    }

    /**
     * A Lucide icon name rendered beside the toast. Omitted when unset, in
     * which case the client falls back to the status's default icon.
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * How long the toast stays visible — milliseconds, or 'persistent' to
     * keep it until dismissed. Omitted when unset so the client uses its own
     * default.
     */
    public function duration(int|string $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function seconds(float $seconds): static
    {
        return $this->duration((int) ($seconds * 1000));
    }

    public function persistent(): static
    {
        return $this->duration('persistent');
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getDuration(): int|string|null
    {
        return $this->duration;
    }

    /**
     * Serialize the notification (docs/CONTRACT.md — "Notification"), omitting
     * every unset field. Only `title` is emitted unconditionally (it is the
     * toast's line); everything else appears when configured.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'title' => $this->title ?? '',
        ];

        if ($this->body !== null) {
            $payload['body'] = $this->body;
        }

        if ($this->status !== null) {
            $payload['status'] = $this->status;
        }

        if ($this->icon !== null) {
            $payload['icon'] = $this->icon;
        }

        if ($this->duration !== null) {
            $payload['duration'] = $this->duration;
        }

        return $payload;
    }

    /**
     * Build the additive success-payload keys for a typed endpoint's OK
     * response. The `notification` object is emitted when one is configured;
     * the plain `message` string is always emitted when set, so clients that
     * only read the message (and the thousands of pre-3.4 assertions on it)
     * keep working. Either or both are omitted when absent, so an action with
     * neither returns a bare `{ "success": true }` exactly as before.
     *
     * @return array{notification?: array<string, mixed>, message?: string}
     */
    public static function toResponseArray(?self $notification, ?string $message = null): array
    {
        $payload = [];

        if ($notification !== null) {
            $payload['notification'] = $notification->toArray();
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return $payload;
    }
}
