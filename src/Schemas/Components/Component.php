<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Tables\Action;

abstract class Component
{
    use CanBeConfigured;
    use Macroable;

    protected ?string $name = null;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    /**
     * The record this component is bound to when serialized read-only (e.g.
     * an infolist entry resolving its value from the viewed record). Mirrors
     * Filament's `->record()`; never survives serialization — the resolver
     * sets it per request.
     */
    protected mixed $record = null;

    protected ?string $placeholder = null;

    protected ?string $helperText = null;

    /**
     * A short line rendered in the field's label row (slice C5) — mirrors
     * Filament's `hint()`. Serialized as data; the React runtime renders it
     * next to the label with hint icons and hint actions.
     */
    protected ?string $hint = null;

    /** @var array{icon: string, tooltip: string|null}|null */
    protected ?array $hintIcon = null;

    /**
     * Small actions rendered in the field's label row (slice C5) — the Ahram
     * "View client / View statement" idiom. Each action serializes its
     * label/icon/tooltip/url and any client-side `visibleWhenFilled()` rule;
     * closures never survive serialization.
     *
     * @var array<int, Action>
     */
    protected array $hintActions = [];

    protected int|string|bool|float|null $default = null;

    protected bool $isRequired = false;

    protected bool $isDisabled = false;

    protected bool $isReadOnly = false;

    /**
     * Whether the field's value is submitted with the form data. Mirrors
     * Filament's dehydrated(): a `dehydrated(false)` field still renders and
     * displays its value, but is excluded from the submit payload and from
     * server-side validation (docs/CONTRACT.md, "Form submission").
     */
    protected bool $isDehydrated = true;

    protected bool $isHidden = false;

    /** @var array<int, string> */
    protected array $hiddenOn = [];

    /** @var array<int, string> */
    protected array $disabledOn = [];

    protected bool $isAutofocused = false;

    /** @var array<int, string> */
    protected array $validation = [];

    /** @var array<string, string>|null */
    protected ?array $options = null;

    /** @var array<int, string>|null */
    protected ?array $dependsOn = null;

    /** @var array<int, string>|null */
    protected ?array $whenTruthy = null;

    /** @var array<int, string>|null */
    protected ?array $whenFalsy = null;

    protected ?int $maxLength = null;

    protected ?int $columnSpan = null;

    final public function __construct(?string $name = null)
    {
        $this->name($name);

        $this->configure();
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    /**
     * The snake_case component type used as the renderer registry key, e.g. "text_input".
     */
    abstract public function getType(): string;

    public function name(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Treat the label as a translation key resolved through the app's
     * translator when the component is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

        return $this;
    }

    public function helperText(?string $helperText): static
    {
        $this->helperText = $helperText;

        return $this;
    }

    /**
     * A short line rendered in the field's label row, mirroring Filament's
     * `hint()` — the label-row slot that in Filament hosts hint text, icons
     * and hint actions (slice C5).
     */
    public function hint(?string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    /**
     * An icon with an optional tooltip rendered in the label row, mirroring
     * Filament's `hintIcon()`.
     */
    public function hintIcon(string $icon, ?string $tooltip = null): static
    {
        $this->hintIcon = ['icon' => $icon, 'tooltip' => $tooltip];

        return $this;
    }

    /**
     * Small actions rendered in the label row, mirroring Filament's
     * `hintActions()`. Each action serializes its label/icon/tooltip/url and
     * any client-side `visibleWhenFilled()` rule; closures never survive
     * serialization (docs/CONTRACT.md, "Fields").
     *
     * @param  array<int, Action>  $actions
     */
    public function hintActions(array $actions): static
    {
        $this->hintActions = $actions;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    /**
     * @return array{icon: string, tooltip: string|null}|null
     */
    public function getHintIcon(): ?array
    {
        return $this->hintIcon;
    }

    /**
     * @return array<int, Action>
     */
    public function getHintActions(): array
    {
        return $this->hintActions;
    }

    public function default(int|string|bool|float|null $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function required(bool $condition = true): static
    {
        $this->isRequired = $condition;

        if ($condition && ! in_array('required', $this->validation, true)) {
            $this->validation[] = 'required';
        }

        return $this;
    }

    public function disabled(bool $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

    /**
     * Render the field read-only: the value displays but cannot be edited
     * (mirrors Filament's readOnly()). Unlike `disabled()`, the value still
     * submits with the form unless `dehydrated(false)` is also set — the
     * Ahram idiom `->readOnly()->dehydrated()` for computed totals.
     */
    public function readOnly(bool $condition = true): static
    {
        $this->isReadOnly = $condition;

        return $this;
    }

    /**
     * Whether the field's value is included in the form data on submit.
     * `dehydrated(false)` keeps the field rendered but excludes its value
     * from the payload and from validation — the Ahram idiom for computed
     * read-only numbers that are shown but never saved.
     */
    public function dehydrated(bool $condition = true): static
    {
        $this->isDehydrated = $condition;

        return $this;
    }

    /**
     * Hide this field only for the named operations ('create' | 'edit' |
     * 'view'), mirroring Filament's `hiddenOn(Operation::Create)`. The
     * operation flows from the serializing context — the resource page or
     * the modal action's type — so one shared form renders differently per
     * operation with no client-side branching (slice C6).
     *
     * @param  string|array<int, string>  $operations
     */
    public function hiddenOn(string|array $operations): static
    {
        $this->hiddenOn = Arr::wrap($operations);

        return $this;
    }

    /**
     * Disable this field only for the named operations ('create' | 'edit' |
     * 'view'), mirroring Filament's `disabledOn(Operation::Edit)`.
     *
     * @param  string|array<int, string>  $operations
     */
    public function disabledOn(string|array $operations): static
    {
        $this->disabledOn = Arr::wrap($operations);

        return $this;
    }

    public function hidden(bool $condition = true): static
    {
        $this->isHidden = $condition;

        return $this;
    }

    public function autofocus(bool $condition = true): static
    {
        $this->isAutofocused = $condition;

        return $this;
    }

    /**
     * @param  array<int, string>  $rules
     */
    public function validation(array $rules): static
    {
        $this->validation = $rules;

        // required() may have been called before validation(); keep its rule
        // in sync so the server never accepts an empty required field.
        if ($this->isRequired && ! in_array('required', $this->validation, true)) {
            $this->validation[] = 'required';
        }

        return $this;
    }

    /**
     * The Laravel validation rules for this field — the server-authoritative
     * copy, never trusted client-side (docs/CONTRACT.md, "Validation").
     *
     * @return array<int, string>
     */
    public function getValidationRules(): array
    {
        return $this->validation;
    }

    /**
     * Append validation rules without replacing existing ones.
     *
     * @param  array<int, string>  $rules
     */
    protected function pushValidationRules(array $rules): static
    {
        foreach ($rules as $rule) {
            if (! in_array($rule, $this->validation, true)) {
                $this->validation[] = $rule;
            }
        }

        return $this;
    }

    /**
     * The value => label options map (or a backed enum to derive it from —
     * `Select::make('status')->options(DemoStatus::class)`, mirroring
     * Filament's `Select::options(SomeEnum::class)`).
     *
     * @param  array<string, string>|class-string<BackedEnum>|BackedEnum  $options
     */
    public function options(array|string|BackedEnum $options): static
    {
        $this->options = is_array($options) ? $options : $this->enumToOptions($options);

        return $this;
    }

    /**
     * Convert a backed enum (class name or instance) to the value => label
     * options map. Labels come from the enum's `getLabel()` when it declares
     * one (Filament's HasLabel contract), falling back to the humanized case
     * name.
     *
     * @param  class-string<BackedEnum>|BackedEnum  $enum
     * @return array<string, string>
     */
    protected function enumToOptions(string|BackedEnum $enum): array
    {
        $class = $enum instanceof BackedEnum ? $enum::class : $enum;

        if (! is_subclass_of($class, BackedEnum::class)) {
            throw new LogicException('options() expects a backed enum class or an array of value => label options.');
        }

        $options = [];

        foreach ($class::cases() as $case) {
            $options[(string) $case->value] = method_exists($class, 'getLabel') ? (string) $case->getLabel() : Str::headline($case->name); // @phpstan-ignore method.notFound
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function dependsOn(array $fields): static
    {
        $this->dependsOn = $fields;

        return $this;
    }

    /**
     * Show this component only while every named sibling field is truthy
     * (mirrors Filament's `whenTruthy()`, evaluated client-side — no round trip).
     *
     * @param  string|array<int, string>  $fields
     */
    public function whenTruthy(string|array $fields): static
    {
        $this->whenTruthy = Arr::wrap($fields);

        return $this;
    }

    /**
     * Show this component only while every named sibling field is falsy
     * (mirrors Filament's `whenFalsy()`, evaluated client-side — no round trip).
     *
     * @param  string|array<int, string>  $fields
     */
    public function whenFalsy(string|array $fields): static
    {
        $this->whenFalsy = Arr::wrap($fields);

        return $this;
    }

    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * The number of grid columns this component spans (1-12, Tailwind's
     * default grid). Clamped to the supported domain so the serialized value
     * always matches what the renderer can express.
     */
    public function columnSpan(?int $columnSpan): static
    {
        $this->columnSpan = $columnSpan === null ? null : min(max($columnSpan, 1), 12);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Bind the record this component serializes against (used by read-only
     * components — infolist entries resolve their value from it). Mirrors
     * Filament's `->record()`; the closure never survives serialization.
     */
    public function record(mixed $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    public function getDefault(): int|string|bool|float|null
    {
        return $this->default;
    }

    public function getLabel(): string
    {
        $label = $this->label ?? Str::headline((string) $this->name);

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function isReadOnly(): bool
    {
        return $this->isReadOnly;
    }

    public function isDehydrated(): bool
    {
        return $this->isDehydrated;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function isAutofocused(): bool
    {
        return $this->isAutofocused;
    }

    public function isVisible(): bool
    {
        return ! $this->isHidden();
    }

    /**
     * Whether the field is hidden for the given operation — a global hidden()
     * or a `hiddenOn()` match. With no operation (null), only the global flag
     * counts.
     */
    public function isHiddenFor(?string $operation): bool
    {
        return $this->isHidden() || ($operation !== null && in_array($operation, $this->hiddenOn, true));
    }

    /**
     * Whether the field is disabled for the given operation — a global
     * disabled() or a `disabledOn()` match.
     */
    public function isDisabledFor(?string $operation): bool
    {
        return $this->isDisabled() || ($operation !== null && in_array($operation, $this->disabledOn, true));
    }

    public function isVisibleFor(?string $operation): bool
    {
        return ! $this->isHiddenFor($operation);
    }

    /**
     * Serialize the component to its JSON contract node (docs/CONTRACT.md).
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'placeholder' => $this->placeholder,
            'helperText' => $this->helperText,
            'hint' => $this->hint,
            'hintIcon' => $this->hintIcon,
            'hintActions' => $this->hintActions !== []
                ? array_map(static fn (Action $action): array => $action->toArray(), $this->hintActions)
                : null,
            'default' => $this->default,
            'required' => $this->isRequired() ? true : null,
            'validation' => $this->validation !== [] ? $this->validation : null,
            'options' => $this->options !== null ? $this->serializeOptions() : null,
            'dependsOn' => $this->dependsOn,
            'whenTruthy' => $this->whenTruthy,
            'whenFalsy' => $this->whenFalsy,
            'hidden' => $this->isHiddenFor($operation) ? true : null,
            'disabled' => $this->isDisabledFor($operation) ? true : null,
            'readOnly' => $this->isReadOnly() ? true : null,
            'dehydrated' => $this->isDehydrated() ? null : false,
            'autofocus' => $this->isAutofocused() ? true : null,
            'maxLength' => $this->maxLength,
            'columnSpan' => $this->columnSpan,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function filterNullValues(array $values): array
    {
        return collect($values)
            ->reject(fn (mixed $value): bool => $value === null)
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function serializeOptions(): array
    {
        if ($this->options === null) {
            return [];
        }

        return $this->serializeOptionMap($this->options);
    }

    /**
     * Normalize a value => label map to the contract's options shape.
     *
     * @param  array<string|int, string>  $options
     * @return array<int, array{value: string, label: string}>
     */
    protected function serializeOptionMap(array $options): array
    {
        return (new Collection($options))
            ->map(fn (string $label, int|string $value): array => [
                'value' => (string) $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>|null
     */
    public function getDependsOn(): ?array
    {
        return $this->dependsOn;
    }

    /**
     * @return array<int, string>|null
     */
    public function getWhenTruthy(): ?array
    {
        return $this->whenTruthy;
    }

    /**
     * @return array<int, string>|null
     */
    public function getWhenFalsy(): ?array
    {
        return $this->whenFalsy;
    }
}
