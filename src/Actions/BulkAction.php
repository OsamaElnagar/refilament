<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

use BackedEnum;
use Closure;
use Illuminate\Auth\Access\Response as AuthResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Actions\Concerns\CanBeAuthorized;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use Refilament\Refilament\Support\Icons\IconManager;
use UnitEnum;

/**
 * Bulk action (slice 2.2) — the base class for toolbar actions, in the same
 * unified namespace as every other action (mirroring Filament, where
 * `BulkAction` lives alongside `Action` in `Filament\Actions`).
 *
 * Mirrors Filament's BulkAction: a named behavior that a user triggers once
 * against every currently-selected row of a table, through the typed bulk
 * endpoint (docs/CONTRACT.md, "Bulk actions"). The `action()` closure never
 * survives serialization — the table resolver rebuilds it server-side when a
 * request arrives, and it receives the whole set of selected records as an
 * Eloquent Collection, never a single record.
 *
 * Policy-backed authorization (slice 4.1) ships on the shared
 * CanBeAuthorized trait, exactly like row actions — `authorize()` /
 * `authorizeAny()` declare abilities the current panel user must pass before
 * the bulk action renders or runs (permissive default: no policy → allowed).
 * Per-record checks use `authorizeIndividualRecords()` below.
 */
class BulkAction
{
    use CanBeAuthorized;
    use CanBeConfigured;
    use EvaluatesClosures;
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected ?string $color = null;

    /**
     * An icon rendered next to the bulk action label (a lucide key the React
     * runtime maps) — mirrors Filament's BulkAction::icon(). Omitted from the
     * payload when unset.
     */
    protected ?string $icon = null;

    protected bool $requiresConfirmation = false;

    protected ?Closure $action = null;

    /**
     * Per-record, policy-backed authorization for bulk actions (slice 4.1 —
     * mirrors Filament's `BulkAction::authorizeIndividualRecords()`). When set,
     * each selected record is checked against the record's model policy before
     * the `action()` closure runs; records the current user cannot act on are
     * filtered out of the collection the closure receives. Null (or false)
     * means no per-record check — the bulk action acts on whatever is selected.
     */
    protected bool|string|UnitEnum|Closure|null $authorizeIndividualRecords = null;

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
     * The name a built-in bulk action falls back to when `make()` gets none —
     * Filament's getDefaultName(). Plain bulk actions return null.
     */
    public static function getDefaultName(): ?string
    {
        return null;
    }

    /**
     * Runs right after the global configureUsing() pipeline, before the
     * builder's own fluent calls — Filament's setUp() position. Built-in
     * bulk actions (DeleteBulkAction, …) put their defaults here.
     */
    protected function setUp(): void {}

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Treat the bulk action label as a translation key resolved through the
     * app's translator when the action is serialized. Mirrors Filament's
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

    public function requiresConfirmation(bool $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    /**
     * The behavior run on the server when the bulk action is triggered. The
     * closure receives the selected records as an Eloquent Collection.
     */
    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Authorize each selected record against its model policy before the bulk
     * action runs (slice 4.1 — mirrors Filament's
     * `BulkAction::authorizeIndividualRecords()`). Pass a policy ability name
     * (checked per record), a UnitEnum ability, a closure returning a bool or
     * Response, or `true` with a closure supplied later. Records the current
     * user cannot act on are filtered out of the collection handed to
     * `action()`.
     */
    public function authorizeIndividualRecords(bool|string|UnitEnum|Closure|null $callback = true): static
    {
        $this->authorizeIndividualRecords = $callback;

        return $this;
    }

    public function shouldAuthorizeIndividualRecords(): bool
    {
        return filled($this->authorizeIndividualRecords) && ($this->authorizeIndividualRecords !== false);
    }

    /**
     * The authorization decision for one selected record (slice 4.1). A string
     * or UnitEnum declares a policy ability inspected for the current panel
     * user against the record; a closure is evaluated with the record and
     * coerced to a Response.
     */
    public function getIndividualRecordAuthorizationResponse(Model $record): AuthResponse
    {
        $callback = $this->authorizeIndividualRecords;

        if (is_string($callback) || $callback instanceof UnitEnum) {
            $ability = $callback instanceof UnitEnum ? $callback->name : $callback;

            $user = app(Refilament::class)->authorizationUser();

            // Permissive default, mirroring Resource::getAuthorizationResponse
            // and CanBeAuthorized: an ability the record's model policy does
            // not declare is allowed, so a bulk action declaring one never
            // locks records out on a fresh install with no policies.
            $policy = Gate::getPolicyFor($record);

            if ($policy === null || ! method_exists($policy, $ability)) {
                return AuthResponse::allow();
            }

            return Gate::forUser($user)->inspect($ability, [$record]);
        }

        if ($callback instanceof Closure) {
            $resolved = $this->evaluate(
                $callback,
                ['record' => $record],
                [get_class($record) => $record],
            );

            if ($resolved instanceof AuthResponse) {
                return $resolved;
            }

            return $resolved ? AuthResponse::allow() : AuthResponse::deny();
        }

        throw new LogicException(
            "Bulk action [{$this->name}] has no [authorizeIndividualRecords()] resolver — pass an ability name or a closure.",
        );
    }

    /**
     * Filter the selected records down to those the current user is authorized
     * to act on (slice 4.1). With no per-record authorization set the set is
     * returned unchanged; otherwise unauthorized records are removed before the
     * `action()` closure runs.
     *
     * @param  EloquentCollection<int, Model>  $records
     * @return EloquentCollection<int, Model>
     */
    public function filterRecords(EloquentCollection $records): EloquentCollection
    {
        if (! $this->shouldAuthorizeIndividualRecords()) {
            return $records;
        }

        return $records->filter(
            fn (Model $record): bool => $this->getIndividualRecordAuthorizationResponse($record)->allowed(),
        );
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

    public function icon(string|BackedEnum|null $icon): static
    {
        $this->icon = IconManager::normalize($icon);

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
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

        $this->evaluate($this->action, ['records' => $records]);
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

        if ($this->icon !== null) {
            $payload['icon'] = $this->icon;
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
