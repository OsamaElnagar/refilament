<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Notifications\Notification;
use Refilament\Refilament\Schemas\Components\Component;
use Refilament\Refilament\Schemas\Components\Layout;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;

class Schema
{
    use CanBeConfigured;
    use Macroable;

    /**
     * Version of the JSON contract this package emits (docs/CONTRACT.md).
     */
    public const int CONTRACT_VERSION = 1;

    /** @var array<int, Component> */
    protected array $components = [];

    protected ?string $id = null;

    /**
     * The record this schema is bound to when serialized read-only (e.g. an
     * infolist). Propagated to every component (entries resolve their values
     * from it). Mirrors Filament's `->record()`; never survives serialization.
     */
    protected mixed $record = null;

    /**
     * Server-side handler run when the form is submitted through the typed
     * submit endpoint (docs/CONTRACT.md, "Form submission"). Never
     * serialized — the schema resolver rebuilds it per request.
     *
     * @var Closure(array<string, mixed>): void|null
     */
    protected ?Closure $submitHandler = null;

    /**
     * Server-side handler run when the form is submitted through the typed
     * record update endpoint (slice 1.7 — docs/CONTRACT.md, "Record pages").
     * Receives the resolved record and the validated data. Never serialized.
     * When unset, the update endpoint defaults to `$record->update($data)`
     * (mass assignment), mirroring Filament's default EditRecord save.
     *
     * @var Closure(mixed, array<string, mixed>): mixed|null
     */
    protected ?Closure $updateHandler = null;

    protected ?string $successMessage = null;

    protected ?string $updateSuccessMessage = null;

    protected ?Notification $successNotification = null;

    protected ?Notification $updateSuccessNotification = null;

    public function __construct()
    {
        $this->configure();
    }

    /**
     * The id clients use to address this schema document when calling typed
     * endpoints such as resolve-options (docs/CONTRACT.md).
     */
    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Bind the record this schema serializes against (used by read-only
     * infolist documents — entries resolve their value from it). Propagates
     * to every component and layout child so a nested tree all resolves
     * against the same record. Mirrors Filament's `->record()`.
     */
    public function record(mixed $record): static
    {
        $this->record = $record;

        foreach ($this->getComponentsRecursively() as $component) {
            $component->record($record);
        }

        return $this;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    public static function make(): static
    {
        // @phpstan-ignore new.static (fluent factory, mirrors Filament's make())
        return new static;
    }

    /**
     * @param  array<int, Component>|Component  $components
     */
    public function components(array|Component $components): static
    {
        $this->components = array_merge($this->components, Arr::wrap($components));

        return $this;
    }

    /**
     * @return array<int, Component>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Find a component by name anywhere in the schema tree, including inside
     * layout components (grid/section).
     */
    public function getComponentByName(string $name): ?Component
    {
        foreach ($this->getComponentsRecursively() as $component) {
            if ($component->getName() === $name) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Every component in the schema tree, depth-first, layouts included.
     *
     * @return array<int, Component>
     */
    public function getComponentsRecursively(): array
    {
        $components = [];

        foreach ($this->components as $component) {
            $components[] = $component;

            if ($component instanceof Layout) {
                array_push($components, ...$component->getAllChildComponents());
            }
        }

        return $components;
    }

    /**
     * Initial values for the form, keyed by field name, taken from the
     * fields' `default()`s. The single derivation point for a form's
     * starting values — shared by Resource::formData() and the typed
     * document endpoint, so a modal form and the full-page create form
     * always present the same values (docs/CONTRACT.md, "Modal actions").
     *
     * @return array<string, int|string|bool|float|null>
     */
    public function initialData(): array
    {
        $data = [];

        foreach ($this->getComponentsRecursively() as $component) {
            $name = $component->getName();

            if ($name !== null) {
                $data[$name] = $component->getDefault();
            }
        }

        return $data;
    }

    /**
     * Register the handler run with the validated data when this form is
     * submitted through the typed submit endpoint. The closure never
     * survives serialization — resolve the schema by id and it is rebuilt.
     *
     * @param  Closure(array<string, mixed>): void  $handler
     */
    public function submitUsing(Closure $handler): static
    {
        $this->submitHandler = $handler;

        return $this;
    }

    public function successMessage(?string $message): static
    {
        $this->successMessage = $message;

        return $this;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    /**
     * A richer toast shown after a create-form submission succeeds (slice
     * 3.4). Precedes the plain `successMessage()`.
     */
    public function successNotification(Notification $notification): static
    {
        $this->successNotification = $notification;

        return $this;
    }

    public function getSuccessNotification(): ?Notification
    {
        return $this->successNotification;
    }

    /**
     * Register the handler run with the validated data when this form is
     * updated through the typed record update endpoint (slice 1.7). The
     * closure receives the resolved record and the validated data; never
     * serialized. When unset, the endpoint defaults to
     * `$record->update($data)`.
     *
     * @param  Closure(mixed $record, array<string, mixed>): mixed  $handler
     */
    public function updateUsing(Closure $handler): static
    {
        $this->updateHandler = $handler;

        return $this;
    }

    public function updateSuccessMessage(?string $message): static
    {
        $this->updateSuccessMessage = $message;

        return $this;
    }

    public function getUpdateSuccessMessage(): ?string
    {
        return $this->updateSuccessMessage;
    }

    /**
     * A richer toast shown after an edit-form update succeeds (slice 3.4).
     * Precedes the plain `updateSuccessMessage()`.
     */
    public function updateSuccessNotification(Notification $notification): static
    {
        $this->updateSuccessNotification = $notification;

        return $this;
    }

    public function getUpdateSuccessNotification(): ?Notification
    {
        return $this->updateSuccessNotification;
    }

    /**
     * Run the update handler against a record with validated data. Defaults
     * to a plain mass-assignment update when no updateUsing() handler is
     * registered (the model must allow mass assignment, like Filament's
     * default EditRecord save).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(mixed $record, array $data): mixed
    {
        if ($this->updateHandler === null) {
            return $record->update($data);
        }

        return ($this->updateHandler)($record, $data);
    }

    /**
     * Run the submit handler against validated data.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data): void
    {
        if ($this->submitHandler === null) {
            throw new LogicException('Schema must have a [submitUsing()] handler set before it can be submitted.');
        }

        ($this->submitHandler)($data);
    }

    /**
     * Rewrite `unique:table,column` rules to ignore a given record, so a
     * record never rejects its own values (Laravel's unique rule would
     * otherwise fail an unchanged slug against itself). Shared by the typed
     * record update endpoint and the live field-validation endpoint.
     *
     * @param  array<string, array<int, string>>  $rules
     * @return array<string, array<int, string>>
     */
    public function ignoreCurrentRecordInUniqueRules(array $rules, string $recordKey): array
    {
        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = array_map(
                static fn (string $rule): string => preg_match('/^unique:([^,]+),([^,]+)$/', $rule, $matches) === 1
                    ? "unique:{$matches[1]},{$matches[2]},{$recordKey}"
                    : $rule,
                $fieldRules,
            );
        }

        return $rules;
    }

    /**
     * The Laravel validation rules map for this schema document, keyed by
     * field name (docs/CONTRACT.md, "Validation"). Collected from every
     * field in the tree; hidden fields and `dehydrated(false)` fields (slice
     * C4 — shown but never submitted) never validate.
     *
     * @return array<string, array<int, string>>
     */
    public function getValidationRules(?string $operation = null): array
    {
        $rules = [];

        foreach ($this->getComponentsRecursively() as $component) {
            $name = $component->getName();

            if ($name === null || ! $component->isVisibleFor($operation) || ! $component->isDehydrated()) {
                continue;
            }

            $componentRules = $component->getValidationRules();

            if ($componentRules !== []) {
                $rules[$name] = $componentRules;
            }
        }

        return $rules;
    }

    /**
     * Field names mapped to their labels so validator messages read naturally
     * ("The Title field is required."), mirroring Filament's validation
     * attributes.
     *
     * @return array<string, string>
     */
    public function getValidationAttributes(): array
    {
        $attributes = [];

        foreach ($this->getComponentsRecursively() as $component) {
            $name = $component->getName();

            if ($name !== null && $component->isVisible()) {
                $attributes[$name] = $component->getLabel();
            }
        }

        return $attributes;
    }

    /**
     * Serialize the schema document (docs/CONTRACT.md).
     *
     * @return array{id?: string, contract: int, schema: array<int, array<string, mixed>>}
     */
    public function toArray(?string $operation = null): array
    {
        $payload = [
            'contract' => self::CONTRACT_VERSION,
            'schema' => array_map(
                static fn (Component $component): array => $component->toArray($operation),
                $this->components,
            ),
        ];

        if ($this->id !== null) {
            $payload = ['id' => $this->id] + $payload;
        }

        return $payload;
    }
}
