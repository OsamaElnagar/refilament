<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Closure;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Tables\Concerns\CanBeAuthorized;

/**
 * Table action (slice 9).
 *
 * Mirrors Filament's Action: a named behavior triggered per record through the
 * typed action endpoint (docs/CONTRACT.md, "Tables"), or — for header actions
 * (slice 1.1, docs/CONTRACT.md, "Modal actions") — a modal that hosts the
 * resource's form. The `action()` closure never survives serialization — the
 * table resolver rebuilds it server-side when a request arrives.
 *
 * Policy-backed authorization (slice 4.1) ships on the shared
 * CanBeAuthorized trait: `authorize()` / `authorizeAny()` declare policy
 * abilities the current panel user must pass before the action renders or
 * runs, with a permissive default (no policy → allowed).
 *
 * Deferred: icons, tooltips, action groups, confirmation with custom text,
 * disabled states, success/failure notifications with titles, configurable
 * modal headings (the modal titles itself from the action label today).
 */
class Action
{
    use CanBeAuthorized;
    use CanBeConfigured;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

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

    /**
     * Where the action navigates on click — used by global-search result
     * actions (slice 3.5). Mirrors Filament's `Action::url()`: the honest
     * request/response model for a search-result action is plain navigation,
     * not a fake Livewire call. When set, the React runtime follows it with a
     * router visit; it never serializes a closure.
     */
    protected ?string $url = null;

    /**
     * An icon rendered next to the action label, given by name (e.g. a
     * lucide/heroicon key the React runtime maps). Mirrors Filament's
     * `Action::icon()`; omitted from the payload when unset.
     */
    protected ?string $icon = null;

    /**
     * A short hint shown on hover, mirroring Filament's `Action::tooltip()`.
     * Omitted from the payload when unset.
     */
    protected ?string $tooltip = null;

    /**
     * Whether a URL action opens in a new tab (mirrors Filament's
     * `Action::openUrlInNewTab()`). The React runtime opens the URL in a new
     * tab when set; otherwise it router-visits in place.
     */
    protected bool $opensUrlInNewTab = false;

    /**
     * Field names that must all hold a non-empty value for this action to
     * render — the serialized client-side rule replacing Filament's runtime
     * `visible(fn (Get $get) => ...)` for hint actions (the Ahram idiom
     * `visible(fn (Get $get) => (bool) $get('client_id'))`). Evaluated in
     * the React runtime against live form state; never a server closure.
     *
     * @var array<int, string>
     */
    protected array $visibleWhenFilled = [];

    /**
     * Whether the modal renders as a drawer (slide-over) instead of a
     * centered dialog (slice 2.7) — mirrors Filament's `Action::slideOver()`.
     * When true, the React runtime hosts the form in the shadcn Drawer
     * primitive mounted from the configured edge.
     */
    protected bool $isSlideOver = false;

    /**
     * The drawer's edge: 'start' (left in LTR) or 'end' (right in LTR).
     * Mirrors Filament's SlideOverPosition. Null means 'end' (the common
     * admin slide-over edge).
     */
    protected ?string $slideOverPosition = null;

    /** @var Closure(object, array<string, mixed>): mixed|null */
    protected ?Closure $action = null;

    /** @var Closure(object): bool|null */
    protected ?Closure $visible = null;

    protected ?string $successMessage = null;

    protected ?Notification $successNotification = null;

    final public function __construct(protected ?string $name = null)
    {
        $this->configure();
    }

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
     * Treat the action label as a translation key resolved through the app's
     * translator when the action is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

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
     * Make the action a pure link that navigates to the given URL when
     * clicked. Targeted at global-search result actions (slice 3.5), where a
     * per-record navigation is the natural behavior; row/header actions keep
     * running through the typed action endpoint instead.
     */
    public function url(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * An icon rendered next to the action label, given by name (a lucide /
     * heroicon key the React runtime maps). Mirrors Filament's `Action::icon()`.
     */
    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * A short hint shown on hover, mirroring Filament's `Action::tooltip()`.
     */
    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function getTooltip(): ?string
    {
        return $this->tooltip;
    }

    /**
     * Open the URL action in a new tab instead of navigating in place
     * (mirrors Filament's `Action::openUrlInNewTab()`).
     */
    public function openUrlInNewTab(bool $condition = true): static
    {
        $this->opensUrlInNewTab = $condition;

        return $this;
    }

    /**
     * Only render this action while every named field holds a non-empty
     * value — the serializable client-side counterpart to Filament's runtime
     * `visible(fn (Get $get) => (bool) $get('client_id'))` (slice C5).
     *
     * @param  string|array<int, string>  $fields
     */
    public function visibleWhenFilled(string|array $fields): static
    {
        $this->visibleWhenFilled = (array) $fields;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getVisibleWhenFilled(): array
    {
        return $this->visibleWhenFilled;
    }

    public function opensUrlInNewTab(): bool
    {
        return $this->opensUrlInNewTab;
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
        $label = $this->label ?? ucfirst((string) $this->name);

        return $this->shouldTranslateLabel ? __($label) : $label;
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
     * Whether the action should render for a given record (slice 4.1). Both a
     * declared authorization (policy check) and the per-record `visible()`
     * closure must pass; an unauthorized action neither renders on the row nor
     * runs through the action endpoint (which re-checks this before invoking
     * the closure). Defaults to true when neither is set.
     */
    public function isVisibleFor(object $record): bool
    {
        if (! $this->isAuthorizedFor($record)) {
            return false;
        }

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

        if ($this->url !== null) {
            $payload['url'] = $this->url;

            if ($this->opensUrlInNewTab) {
                $payload['openUrlInNewTab'] = true;
            }
        }

        if ($this->visibleWhenFilled !== []) {
            $payload['visibleWhenFilled'] = $this->visibleWhenFilled;
        }

        if ($this->icon !== null) {
            $payload['icon'] = $this->icon;
        }

        if ($this->tooltip !== null) {
            $payload['tooltip'] = $this->tooltip;
        }

        return $payload;
    }
}
