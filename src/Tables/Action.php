<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Closure;
use LogicException;
use Refilament\Refilament\Notifications\Notification;

/**
 * Table action (slice 9).
 *
 * Mirrors Filament's Action: a named behavior triggered per record through the
 * typed action endpoint (docs/CONTRACT.md, "Tables"), or — for header actions
 * (slice 1.1, docs/CONTRACT.md, "Modal actions") — a modal that hosts the
 * resource's form. The `action()` closure never survives serialization — the
 * table resolver rebuilds it server-side when a request arrives.
 *
 * Deferred: icons, tooltips, action groups, confirmation with custom text,
 * disabled states, success/failure notifications with titles, configurable
 * modal headings (the modal titles itself from the action label today).
 */
class Action
{
    protected ?string $label = null;

    protected ?string $color = null;

    /**
     * The modal behavior: 'create' (opens the linked form in a dialog),
     * 'edit' or 'delete' (both wired in slice 1.2). Row actions carry no
     * type — they run inline through the typed action endpoint.
     */
    protected ?string $type = null;

    /**
     * The id of the form schema document a modal action submits through
     * (registered via Refilament::registerSchemaResolver()).
     */
    protected ?string $schema = null;

    protected bool $requiresConfirmation = false;

    /** @var Closure(object, array<string, mixed>): mixed|null */
    protected ?Closure $action = null;

    /** @var Closure(object): bool|null */
    protected ?Closure $visible = null;

    protected ?string $successMessage = null;

    protected ?Notification $successNotification = null;

    final public function __construct(protected ?string $name = null) {}

    public static function make(?string $name = null): static
    {
        return new static($name);
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

    /**
     * The modal behavior this action triggers. 'create' renders the linked
     * form in a dialog and submits it through the typed submit endpoint;
     * 'edit' and 'delete' land with slice 1.2. Row actions are typed
     * through the action endpoint instead and need no type.
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * The id of the form schema document this modal action hosts — the
     * client fetches it from the typed document endpoint and submits through
     * the typed submit endpoint (docs/CONTRACT.md, "Modal actions").
     */
    public function schema(?string $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function requiresConfirmation(bool $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    /**
     * The behavior run on the server when the action is triggered. Receives
     * the record, plus the validated form data when the action links a
     * schema document (modal edit — slice 1.2).
     *
     * @param  Closure(object, array<string, mixed>): mixed  $action
     */
    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Per-record visibility, evaluated at serialization time. The closure
     * receives the record and must return a bool. Rows carry only the names of
     * actions that are visible for that record.
     *
     * @param  Closure(object): bool  $visible
     */
    public function visible(Closure $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function successMessage(string $successMessage): static
    {
        $this->successMessage = $successMessage;

        return $this;
    }

    /**
     * A richer toast shown after the action succeeds (slice 3.4). Precedes the
     * plain `successMessage()`: when set, the action endpoint returns this
     * notification and the client renders it as a titled sonner toast, falling
     * back to the message (or the action label) otherwise.
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSchema(): ?string
    {
        return $this->schema;
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
     * @return Closure(object, array<string, mixed>): mixed|null
     */
    public function getAction(): ?Closure
    {
        return $this->action;
    }

    /**
     * @return Closure(object): bool|null
     */
    public function getVisible(): ?Closure
    {
        return $this->visible;
    }

    /**
     * Run the action's closure against a record. Closures declared with a
     * single parameter (row actions like publish/delete) simply ignore the
     * second argument, so the same call shape serves both.
     *
     * @param  array<string, mixed>  $data
     */
    public function call(object $record, array $data = []): mixed
    {
        if (! $this->action instanceof Closure) {
            throw new LogicException("Action [{$this->name}] has no [action()] closure set.");
        }

        return ($this->action)($record, $data);
    }

    /**
     * Whether the action should render for a given record. Defaults to true
     * when no visibility closure is set.
     */
    public function isVisibleFor(object $record): bool
    {
        if (! $this->visible instanceof Closure) {
            return true;
        }

        return (bool) ($this->visible)($record);
    }

    /**
     * Serialize the action definition (docs/CONTRACT.md, "Tables"). Closures
     * are never serialized.
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

        if ($this->type !== null) {
            $payload['type'] = $this->type;
        }

        if ($this->schema !== null) {
            $payload['schema'] = $this->schema;
        }

        if ($this->requiresConfirmation) {
            $payload['requiresConfirmation'] = true;
        }

        return $payload;
    }
}
