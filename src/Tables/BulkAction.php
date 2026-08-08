<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Refilament\Refilament\Notifications\Notification;

/**
 * Toolbar (bulk) action (slice 2.2).
 *
 * Mirrors Filament's BulkAction: a named behavior that a user triggers once
 * against every currently-selected row of a table, through the typed bulk
 * endpoint (docs/CONTRACT.md, "Bulk actions"). The `action()` closure never
 * survives serialization — the table resolver rebuilds it server-side when a
 * request arrives, and it receives the whole set of selected records as an
 * Eloquent Collection, never a single record.
 */
class BulkAction
{
    protected ?string $label = null;

    protected ?string $color = null;

    protected bool $requiresConfirmation = false;

    /** @var Closure(EloquentCollection<int, Model>): mixed|null */
    protected ?Closure $action = null;

    protected ?string $successMessage = null;

    protected ?Notification $successNotification = null;

    final public function __construct(protected ?string $name = null) {}

    public static function make(?string $name = null): static
    {
        return new static($name ?? static::getDefaultName());
    }

    public static function getDefaultName(): ?string
    {
        return null;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * One of: primary, secondary, danger, success, warning, info (the React
     * runtime maps these to Tailwind classes).
     */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function requiresConfirmation(bool $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    /**
     * The behavior run on the server when the bulk action is triggered. The
     * closure receives the selected records as an Eloquent Collection.
     *
     * @param  Closure(EloquentCollection<int, Model>): void  $action
     */
    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function successMessage(string $successMessage): static
    {
        $this->successMessage = $successMessage;

        return $this;
    }

    /**
     * A richer toast shown after the bulk action succeeds (slice 3.4).
     * Precedes the plain `successMessage()`.
     */
    public function successNotification(Notification $notification): static
    {
        $this->successNotification = $notification;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst((string) $this->name);
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isRequiresConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function getSuccessNotification(): ?Notification
    {
        return $this->successNotification;
    }

    /**
     * @return Closure(EloquentCollection<int, Model>): mixed|null
     */
    public function getAction(): ?Closure
    {
        return $this->action;
    }

    /**
     * Run the action's closure against the selected records.
     *
     * @param  EloquentCollection<int, Model>  $records
     */
    public function call(EloquentCollection $records): void
    {
        if (! $this->action instanceof Closure) {
            throw new LogicException("Bulk action [{$this->name}] has no [action()] closure set.");
        }

        ($this->action)($records);
    }

    /**
     * Serialize the bulk action definition (docs/CONTRACT.md, "Bulk actions").
     * Closures are never serialized.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
        ];

        if ($this->color !== null) {
            $payload['color'] = $this->color;
        }

        if ($this->requiresConfirmation) {
            $payload['requiresConfirmation'] = true;
        }

        return $payload;
    }
}
