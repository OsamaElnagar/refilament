<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables;

use Closure;
use Illuminate\Auth\Access\Response as AuthResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Tables\Concerns\CanBeAuthorized;
use UnitEnum;

/**
 * Toolbar (bulk) action (slice 2.2).
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
    use Macroable;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    protected ?string $color = null;

    protected bool $requiresConfirmation = false;

    /** @var Closure(EloquentCollection<int, Model>): mixed|null */
    protected ?Closure $action = null;

    /**
     * Per-record, policy-backed authorization for bulk actions (slice 4.1 —
     * mirrors Filament's `BulkAction::authorizeIndividualRecords()`). When set,
     * each selected record is checked against the record's model policy before
     * the `action()` closure runs; records the current user cannot act on are
     * filtered out of the collection the closure receives. Null (or false)
     * means no per-record check — the bulk action acts on whatever is selected.
     *
     * @var bool|string|UnitEnum|Closure(Model): mixed|null
     */
    protected bool|string|UnitEnum|Closure|null $authorizeIndividualRecords = null;

    protected ?string $successMessage = null;

    protected ?Notification $successNotification = null;

    final public function __construct(protected ?string $name = null)
    {
        $this->configure();
    }

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
     *
     * @param  Closure(EloquentCollection<int, Model>): void  $action
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
            $resolved = $callback($record);

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
