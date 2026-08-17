<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use BackedEnum;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Refilament\Refilament\Actions\Action;
use Refilament\Refilament\Schemas\Get;
use Refilament\Refilament\Support\Concerns\CanBeConfigured;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;

abstract class Component
{
    use CanBeConfigured;
    use EvaluatesClosures;
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

    protected bool|Closure $isRequired = false;

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

    /**
     * The field's validation rules as [rule, condition] pairs — additive
     * via rules()/rule(), each entry gated by a bool or a closure evaluated
     * against the form data (mirrors Filament's CanBeValidated). Rules may
     * be strings, Rule objects, or Laravel closure rules; the latter pass
     * through to the validator untouched, never evaluated. Rule-provider
     * closures (from `rules(fn (Get $get) => [...])`) live in
     * $ruleProviders instead.
     *
     * @var array<int, array{0: string|object|Closure, 1: bool|Closure}>
     */
    protected array $rules = [];

    /**
     * Rule-provider closures — `rules(fn (Get $get) => [...])` — evaluated
     * against the data snapshot at validation time to produce the rules.
     * Distinct from plain rule closures (Laravel closure rules), which ride
     * in $rules and are never evaluated.
     *
     * @var array<int, array{0: Closure, 1: bool|Closure}>
     */
    protected array $ruleProviders = [];

    /** @var array<string, mixed> */
    protected array $validationData = [];

    /** @var array<string|int, string>|null */
    protected ?array $options = null;

    /** @var array<int, string>|null */
    protected ?array $dependsOn = null;

    /** @var array<int, string>|null */
    protected ?array $whenTruthy = null;

    /** @var array<int, string>|null */
    protected ?array $whenFalsy = null;

    protected ?int $maxLength = null;

    protected ?int $columnSpan = null;

    protected ?bool $isVisible = null;

    protected ?bool $liveOnBlur = null;

    protected ?string $autocomplete = null;

    /**
     * Mark the component as visible or hidden.
     * Accepts a static boolean; defaults to null (omitted from payload).
     * When set to true/false, the component includes/excludes the 'visible' key in the serialized payload.
     */
    public function visible(bool $visible): static
    {
        $this->isVisible = $visible;

        return $this;
    }

    /**
     * Mark the component as "live", meaning it will update its value
     * as the user types. Serializes a 'dependsOn' key for the React side
     * to handle reactivity through the dependsOn pattern.
     *
     * @param  string|array<int, string>  $dependencies
     */
    public function live(string|array $dependencies): static
    {
        $this->dependsOn = is_string($dependencies) ? [$dependencies] : $dependencies;

        return $this;
    }

    /**
     * Mark the component to trigger live validation on blur.
     * Serializes a 'liveOnBlur' key for the React side to handle
     * validation when the field loses focus.
     */
    public function liveOnBlur(bool $enabled = true): static
    {
        $this->liveOnBlur = $enabled;

        return $this;
    }

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

    /**
     * Mark the field as required. Accepts a static bool or a closure
     * evaluated against the form data (mirrors Filament's
     * `required(bool|Closure)`): `->required(fn (Get $get): bool =>
     * $get('type') === 'physical')`. The resolved value drives the client's
     * required asterisk (serialized against the form's initial data) and
     * prepends the server-side `required` rule on submit.
     */
    public function required(bool|Closure $condition = true): static
    {
        $this->isRequired = $condition;

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
     * Add validation rules — additive, mirroring Filament's `rules()`.
     *
     * Accepts a pipe-separated string, an array of rules (strings, Rule
     * objects or Laravel closure rules — passed through to the validator
     * untouched), or a closure that receives the form data via a `Get`
     * typed injection and returns the rules, e.g.
     * `->rules(fn (Get $get): array => $get('country') === 'us' ? ['required', 'string'] : [])`.
     * Every entry is gated by `$condition` (a bool or a closure evaluated
     * against the form data).
     *
     * @param  string|array<int, string|object|Closure>|Closure  $rules
     */
    public function rules(string|array|Closure $rules, bool|Closure $condition = true): static
    {
        if ($rules instanceof Closure) {
            $this->ruleProviders[] = [$rules, $condition];

            return $this;
        }

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach ($rules as $rule) {
            $this->rules[] = [$rule, $condition];
        }

        return $this;
    }

    /**
     * Add a single rule — additive, mirroring Filament's `rule()`. A
     * Closure here is a Laravel closure rule
     * (`function ($attribute, $value, $fail)`) and is passed through to the
     * validator untouched, never evaluated.
     */
    public function rule(string|object $rule, bool|Closure $condition = true): static
    {
        $this->rules[] = [$rule, $condition];

        return $this;
    }

    /**
     * @deprecated Use `rules()` instead.
     *
     * @param  array<int, string>  $rules
     */
    public function validation(array $rules): static
    {
        $this->rules = array_map(static fn (string $rule): array => [$rule, true], $rules);
        $this->ruleProviders = [];

        return $this;
    }

    /**
     * The field's resolved Laravel validation rules — the
     * server-authoritative copy, never trusted client-side. Conditions and
     * rule-provider closures evaluate against the current data snapshot
     * (docs/CONTRACT.md, "Validation").
     *
     * @return array<int, string|object|Closure>
     */
    public function getValidationRules(): array
    {
        $rules = [];

        foreach ($this->rules as [$rule, $condition]) {
            if (! $this->evaluate($condition, ...$this->getValidationDependencies())) {
                continue;
            }

            $rules[] = $rule;
        }

        foreach ($this->ruleProviders as [$provider, $condition]) {
            if (! $this->evaluate($condition, ...$this->getValidationDependencies())) {
                continue;
            }

            $resolved = $this->evaluate($provider, ...$this->getValidationDependencies());

            if (is_array($resolved)) {
                array_push($rules, ...array_values($resolved));
            } elseif (is_string($resolved)) {
                array_push($rules, ...explode('|', $resolved));
            } else {
                $rules[] = $resolved;
            }
        }

        // String rules dedupe (first occurrence wins) — field-type helpers
        // like `numeric()` and an explicit `rules([... 'numeric' ...])`
        // naturally overlap. Objects and closure rules always pass through.
        $seen = [];

        $rules = array_values(array_filter(
            $rules,
            function (mixed $rule) use (&$seen): bool {
                if (! is_string($rule)) {
                    return true;
                }

                if (in_array($rule, $seen, true)) {
                    return false;
                }

                $seen[] = $rule;

                return true;
            },
        ));

        return $this->withBaseValidationRules($rules);
    }

    /**
     * Fold the implicit base rule into a resolved rule list — `required`
     * when the field is required, else `nullable` (mirrors Filament's
     * getRequiredValidationRule(), so a non-required field's type rules
     * never reject empty input — Laravel's ConvertEmptyStringsToNull
     * middleware turns `''` into `null` before validation). `nullable` is
     * only added when the field has rules at all, keeping rule-less fields
     * validation-free. Idempotent, so subclasses that append intrinsic
     * rules may call it again.
     *
     * @param  array<int, string|object|Closure>  $rules
     * @return array<int, string|object|Closure>
     */
    protected function withBaseValidationRules(array $rules): array
    {
        if ($this->isRequired()) {
            if (! in_array('required', $rules, true)) {
                array_unshift($rules, 'required');
            }

            return $rules;
        }

        if ($rules !== [] && ! in_array('nullable', $rules, true)) {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * The data snapshot closures evaluate against — submitted values at
     * validation time, initial values at serialization. Set by the schema
     * before rules are collected or the payload is built; never serialized.
     *
     * @param  array<string, mixed>  $data
     */
    public function setValidationData(array $data): static
    {
        $this->validationData = $data;

        return $this;
    }

    /**
     * The `$get` / `Get` injections for closure evaluation — a stateless
     * reader over the data snapshot this component was given (submitted
     * values at validation, initial values at serialization). Named and
     * typed so both `fn ($get)` and `fn (Get $get)` resolve.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function getValidationDependencies(): array
    {
        return [
            ['get' => fn (): Get => new Get($this->validationData)],
            [Get::class => fn (): Get => new Get($this->validationData)],
        ];
    }

    /**
     * The subset of the resolved rules safe to ship to the client — plain
     * strings only (Rule objects and closure rules never serialize). The
     * client uses this solely to detect `unique:` rules for its debounced
     * live check; the rules themselves stay server-authoritative.
     *
     * @return array<int, string>
     */
    protected function getSerializableValidationRules(): array
    {
        // `nullable` is the implicit base rule for non-required fields —
        // folded in server-side, never shipped (the payload shows the rules
        // the developer wrote; the client uses it only to detect `unique:`).
        return array_values(array_filter(
            $this->getValidationRules(),
            static fn (mixed $rule): bool => is_string($rule) && $rule !== 'nullable',
        ));
    }

    /**
     * Append validation rules without replacing existing ones (the helper
     * field-type configurers use, e.g. `integer()` → `['integer']`).
     *
     * @param  array<int, string>  $rules
     */
    protected function pushValidationRules(array $rules): static
    {
        $existing = array_map(
            static fn (array $pair): mixed => $pair[0],
            $this->rules,
        );

        foreach ($rules as $rule) {
            if (! in_array($rule, $existing, true)) {
                $this->rules[] = [$rule, true];
            }
        }

        return $this;
    }

    /**
     * The value => label options map (or a backed enum to derive it from —
     * `Select::make('status')->options(DemoStatus::class)`, mirroring
     * Filament's `Select::options(SomeEnum::class)`).
     *
     * @param  array<string|int, string>|class-string<BackedEnum>|BackedEnum  $options
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

    public function getDefault(): mixed
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
        return (bool) $this->evaluate($this->isRequired, ...$this->getValidationDependencies());
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
        if ($this->isVisible !== null) {
            return $this->isVisible;
        }

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
            'validation' => ($rules = $this->getSerializableValidationRules()) !== [] ? $rules : null,
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
            'visible' => $this->isVisible === false ? true : null,
            'liveOnBlur' => $this->liveOnBlur ? true : null,
            'autocomplete' => $this->autocomplete,
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
