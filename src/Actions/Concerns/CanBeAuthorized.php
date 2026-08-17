<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Auth\Access\Response as AuthResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use UnitEnum;

/**
 * Policy-based authorization for actions (slice 4.1 — mirrors Filament's
 * `CanBeAuthorized` trait). Null means "allowed for all users" (the reality of
 * a fresh install where the panel is open by default); `authorize()` /
 * `authorizeAny()` declare policy abilities that must pass for the current
 * panel user before the action renders or runs.
 *
 * The permissive default mirrors `Resource::getAuthorizationResponse()`: a
 * model with no policy — or a policy that doesn't declare the ability — allows
 * everything. Authorization only engages when a policy declares the ability,
 * so adding an `authorize()` call never locks an action out on a fresh install.
 */
trait CanBeAuthorized
{
    use EvaluatesClosures;

    /**
     * Policy-based authorization config. `authorize()` / `authorizeAny()` build
     * the array shape (`type`, `abilities`, `arguments`); a Closure may also be
     * set directly (via `authorize(Closure)`) — it is the whole authorization,
     * evaluated with the record when the action targets one.
     *
     * @var array{type: 'all'|'any', abilities: array<int, string>, arguments: array<int, mixed>}|Closure|null
     */
    protected mixed $authorization = null;

    /**
     * Require every listed policy ability for the current user (slice 4.1 —
     * mirrors Filament's `Action::authorize()`). When the action targets a
     * record, that record is prepended to `$arguments`, so `->authorize('delete')`
     * on a row action resolves to `Gate::inspect('delete', $record)` for the
     * current panel user. A meta-ability on a record-less action (header create,
     * bulk action) takes the model class as its argument — `->authorize('create',
     * Post::class)`. A Closure passed as `$abilities` is the whole authorization
     * (evaluated with the record when there is one, coerced to a Response).
     *
     * @param  UnitEnum|BackedEnum|string|array<int, UnitEnum|BackedEnum|string>|Closure  $abilities
     * @param  Model|class-string|array<int, mixed>|null  $arguments
     */
    public function authorize(UnitEnum|BackedEnum|string|array|Closure $abilities, Model|string|array|null $arguments = null): static
    {
        if ($abilities instanceof Closure) {
            // A closure is the whole authorization — the record is injected
            // when the action targets one (resolveAuthorizationResponse).
            $this->authorization = $abilities;

            return $this;
        }

        $this->authorization = [
            'type' => 'all',
            'abilities' => array_map(self::normalizeAbility(...), Arr::wrap($abilities)),
            'arguments' => $arguments === null ? [] : Arr::wrap($arguments),
        ];

        return $this;
    }

    /**
     * Require **any one** of the listed policy abilities (slice 4.1 — mirrors
     * Filament's `Action::authorizeAny()`). The check passes when at least one
     * declared ability is allowed (or is undeclared — permissive); otherwise
     * the action is treated as unauthorized and does not render or run.
     *
     * @param  UnitEnum|BackedEnum|string|array<int, UnitEnum|BackedEnum|string>  $abilities
     * @param  Model|class-string|array<int, mixed>|null  $arguments
     */
    public function authorizeAny(UnitEnum|BackedEnum|string|array $abilities, Model|string|array|null $arguments = null): static
    {
        $this->authorization = [
            'type' => 'any',
            'abilities' => array_map(self::normalizeAbility(...), Arr::wrap($abilities)),
            'arguments' => $arguments === null ? [] : Arr::wrap($arguments),
        ];

        return $this;
    }

    /**
     * @return array{type: 'all'|'any', abilities: array<int, string>, arguments: array<int, mixed>}|Closure|null
     */
    public function getAuthorization(): mixed
    {
        return $this->authorization;
    }

    /**
     * Whether the current user may run this action with no record context
     * (record-less actions: header actions, bulk actions). With no
     * authorization declared the action is allowed — the fresh-install
     * default. Mirrors Filament's `Action::isAuthorized()`.
     */
    public function isAuthorized(): bool
    {
        return $this->getAuthorizationResponse()->allowed();
    }

    /**
     * Whether the current user may run this action against the given record
     * (slice 4.1). The record is prepended to the declared arguments before the
     * policy check. Mirrors Filament's `Action::isAuthorized()`.
     */
    public function isAuthorizedFor(object $record): bool
    {
        return $this->getAuthorizationResponseFor($record)->allowed();
    }

    /**
     * The authorization decision for this action with no record context
     * (header/bulk actions). A closure authorization is evaluated (no record to
     * inject) and coerced to a Response; an array declaration is resolved
     * against the declared model's policy through the Gate, for the current
     * panel user — with the permissive default (no policy / undeclared ability
     * → allow) mirroring `Resource::getAuthorizationResponse()`.
     */
    public function getAuthorizationResponse(): AuthResponse
    {
        return $this->resolveAuthorizationResponse([]);
    }

    /**
     * The authorization decision for this action against a record. The record
     * is prepended to the declared arguments — exactly like Filament's
     * `parseAuthorizationArguments()` — so a row action's `authorize('delete')`
     * inspects `delete` against that record.
     */
    public function getAuthorizationResponseFor(object $record): AuthResponse
    {
        return $this->resolveAuthorizationResponse([$record]);
    }

    /**
     * Evaluate the authorization config against the given prepended arguments.
     * A Closure authorization is called with the prepended record (or no
     * arguments for record-less actions); an array declaration inspects each
     * declared ability through the Gate for the current panel user, honoring
     * the permissive default per ability.
     *
     * @param  array<int, mixed>  $prependedArguments
     */
    protected function resolveAuthorizationResponse(array $prependedArguments): AuthResponse
    {
        if ($this->authorization === null) {
            return AuthResponse::allow();
        }

        if ($this->authorization instanceof Closure) {
            // The closure receives the record by name and by its class when
            // the action targets one (row actions); record-less closure
            // authorizations (header/bulk actions) take no record.
            $record = $prependedArguments[0] ?? null;

            $resolved = is_object($record)
                ? $this->evaluate($this->authorization, ['record' => $record], [get_class($record) => $record])
                : $this->evaluate($this->authorization);

            if ($resolved instanceof AuthResponse) {
                return $resolved;
            }

            return $resolved ? AuthResponse::allow() : AuthResponse::deny();
        }

        $arguments = [...$prependedArguments, ...$this->authorization['arguments']];

        $user = app(Refilament::class)->authorizationUser();

        // The subject the declared abilities are inspected against — the
        // prepended record, else the first declared argument (a model instance
        // or a model class-string for meta-abilities like create/deleteAny).
        $subject = $arguments[0] ?? null;

        $policy = is_object($subject) || is_string($subject)
            ? Gate::getPolicyFor($subject)
            : null;

        $anyDeclaredDenied = false;

        foreach ($this->authorization['abilities'] as $ability) {
            // Permissive default: an ability no policy declares can't decide
            // it — it is allowed (mirrors Resource::getAuthorizationResponse,
            // docs/CONTRACT.md "Authorization"). Authorization only engages
            // when a policy declares the ability.
            if ($policy === null || ! method_exists($policy, $ability)) {
                if ($this->authorization['type'] === 'any') {
                    // An undeclared ability satisfies `any` permissively.
                    return AuthResponse::allow();
                }

                continue;
            }

            $response = Gate::forUser($user)->inspect($ability, $arguments);

            if ($response->allowed()) {
                if ($this->authorization['type'] === 'any') {
                    return $response;
                }

                continue;
            }

            // Denied by a declaring policy.
            if ($this->authorization['type'] === 'all') {
                return $response;
            }

            $anyDeclaredDenied = true;
        }

        // `any`: every declared ability was denied (nothing undeclared slipped
        // through — that path returned already), so the action is unauthorized.
        if ($this->authorization['type'] === 'any' && $anyDeclaredDenied) {
            return AuthResponse::deny();
        }

        return AuthResponse::allow();
    }

    /**
     * Normalize a policy ability token to its string form: a BackedEnum
     * resolves to its value, a UnitEnum to its case name, a string passes
     * through — mirroring Filament's CanBeAuthorized handling of enum
     * abilities.
     */
    protected static function normalizeAbility(UnitEnum|BackedEnum|string $ability): string
    {
        return match (true) {
            $ability instanceof BackedEnum => (string) $ability->value,
            $ability instanceof UnitEnum => $ability->name,
            default => $ability,
        };
    }
}
