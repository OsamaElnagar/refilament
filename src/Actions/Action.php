<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use BackedEnum;
use Closure;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Actions\Concerns\CanBeAuthorized;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use Refilament\Refilament\Support\Concerns\HasTooltip;
use Refilament\Refilament\Support\Icons\IconManager;

/**
 * Action (slice 9) — the single base class for every action, in the same
 * unified namespace as all of them (mirroring Filament, where table, page,
 * header and notification actions all come from `Filament\Actions`).
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
    use EvaluatesClosures;
    use HasTooltip;
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
     * actions (slice 3.5) and row navigation (record actions). Mirrors
     * Filament's `Action::url()`: the honest request/response model for a
     * navigation action is a router visit, not a fake Livewire call.
     *
     * Two shapes, both resolved before the payload ships:
     *  - a plain string is a static URL, serialized on the action definition
     *    (global-search result actions);
     *  - a closure `fn ($record) => string` is a per-record URL, evaluated
     *    during row serialization and shipped on the row under
     *    `actionUrls[<name>]` — never serialized (closures don't survive
     *    JSON), so the definition omits it and each row carries its own.
     */
    protected string|Closure|null $url = null;

    /**
     * The resource page name this action navigates to (e.g. 'view') — a
     * per-record URL resolved through the table's URL resolver at row
     * serialization time, the counterpart of `url()` for built-in record
     * actions that don't know their resource (ViewAction). The resolver is
     * wired by the resource layer; an action whose page resolves no URL for
     * a record (no such page, or the user may not view it) is dropped from
     * that record's visible actions.
     */
    protected ?string $urlPage = null;

    /**
     * An icon rendered next to the action label, given by name (e.g. a
     * lucide/heroicon key the React runtime maps). Mirrors Filament's
     * `Action::icon()`; omitted from the payload when unset.
     */
    protected ?string $icon = null;

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

    protected ?Closure $action = null;

    protected ?Closure $visible = null;

    protected ?string $successMessage = null;

    protected ?Notification $successNotification = null;

    /**
     * The confirmation modal's heading and description — custom per-action
     * text replacing the renderer's generic "Confirm {label}" copy (mirrors
     * Filament's modalHeading()/modalDescription()).
     */
    protected ?string $modalHeading = null;

    protected ?string $modalDescription = null;

    final public function __construct(protected ?string $name = null)
    {
        $this->configure();
        $this->setUp();
    }

    public static function make(?string $name = null): static
    {
        return new static($name ?? static::getDefaultName());
    }

    /**
     * The name a built-in action falls back to when `make()` gets none —
     * Filament's getDefaultName() ('delete', 'edit', 'view'). Plain actions
     * return null and keep their anonymous make() behavior.
     */
    public static function getDefaultName(): ?string
    {
        return null;
    }

    /**
     * Runs right after the global `configureUsing()` pipeline, before the
     * builder's own fluent calls — Filament's setUp() position. Built-in
     * actions (DeleteAction, …) put their defaults here so a consumer's
     * `->label()`, `->action()` etc. always win.
     */
    protected function setUp(): void {}

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
     * clicked. Targeted at global-search result actions (slice 3.5) and row
     * record actions; row/header actions otherwise run through the typed
     * action endpoint. A closure receives the record and returns the URL —
     * resolved per row, never serialized.
     */
    public function url(string|Closure|null $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): string|Closure|null
    {
        return $this->url;
    }

    /**
     * Resolve this action's URL for a record — static strings pass through;
     * a closure is evaluated with the record. A closure that resolves null
     * (or an empty string) means "no URL for this record", normalized to
     * null so the table treats it exactly like an unresolvable
     * page-navigation action (dropped from the row's visible actions).
     * Page-navigation actions (urlPage()) resolve through the table's URL
     * resolver instead and are handled by the table during row serialization.
     */
    public function resolveUrl(?object $record = null): ?string
    {
        // A per-record closure can't resolve without the record — treat the
        // URL as absent (mirroring the previous positional call's null
        // guard). The closure receives the record by name and by its class,
        // so both `fn ($record)` and `fn (Product $product)` work.
        if ($this->url instanceof Closure && $record === null) {
            return null;
        }

        $url = $this->evaluate(
            $this->url,
            ['record' => $record],
            $record !== null ? [get_class($record) => $record] : [],
        );

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The name of the resource page this action navigates to, resolved
     * per-record through the table's URL resolver at row serialization
     * (the ViewAction wiring — mirroring Filament, where ViewAction's URL
     * comes from the resource's view page).
     */
    public function urlPage(string $page): static
    {
        $this->urlPage = $page;

        return $this;
    }

    public function getUrlPage(): ?string
    {
        return $this->urlPage;
    }

    /**
     * An icon rendered next to the action label, given by name (a lucide /
     * heroicon key the React runtime maps) or a `Heroicon` enum case. Mirrors
     * Filament's `Action::icon()`.
     */
    public function icon(string|BackedEnum|null $icon): static
    {
        $this->icon = IconManager::normalize($icon);

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
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

    /**
     * The confirmation modal's heading (mirrors Filament's modalHeading()).
     * When unset the renderer falls back to "Confirm {label}".
     */
    public function modalHeading(?string $modalHeading): static
    {
        $this->modalHeading = $modalHeading;

        return $this;
    }

    /**
     * The confirmation modal's description (mirrors Filament's
     * modalDescription()). When unset the renderer falls back to its generic
     * "cannot be undone" copy.
     */
    public function modalDescription(?string $modalDescription): static
    {
        $this->modalDescription = $modalDescription;

        return $this;
    }

    public function getModalHeading(): ?string
    {
        return $this->modalHeading;
    }

    public function getModalDescription(): ?string
    {
        return $this->modalDescription;
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

        return $this->evaluate(
            $this->action,
            ['record' => $record, 'data' => $data],
            [get_class($record) => $record],
        );
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

        return (bool) $this->evaluate(
            $this->visible,
            ['record' => $record],
            [get_class($record) => $record],
        );
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

        // Only a static string URL belongs on the definition — a closure
        // resolves per-record at row serialization and ships on the row
        // (`actionUrls[<name>]`), never here. Page-navigation actions
        // (urlPage()) likewise resolve per-record.
        if (is_string($this->url)) {
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

        if ($this->getTooltip() !== null) {
            $payload['tooltip'] = $this->getTooltip();
        }

        if ($this->modalHeading !== null) {
            $payload['modalHeading'] = $this->modalHeading;
        }

        if ($this->modalDescription !== null) {
            $payload['modalDescription'] = $this->modalDescription;
        }

        return $payload;
    }
}
