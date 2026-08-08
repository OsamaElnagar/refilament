<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class Component
{
    protected ?string $name = null;

    protected ?string $label = null;

    /**
     * The record this component is bound to when serialized read-only (e.g.
     * an infolist entry resolving its value from the viewed record). Mirrors
     * Filament's `->record()`; never survives serialization — the resolver
     * sets it per request.
     */
    protected mixed $record = null;

    protected ?string $placeholder = null;

    protected ?string $helperText = null;

    protected int|string|bool|null $default = null;

    protected bool $isRequired = false;

    protected bool $isDisabled = false;

    protected bool $isHidden = false;

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

    public function helperText(?string $helperText): static
    {
        $this->helperText = $helperText;

        return $this;
    }

    public function default(int|string|bool|null $default): static
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
     * @param  array<string, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
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

    public function getDefault(): int|string|bool|null
    {
        return $this->default;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline((string) $this->name);
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
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
     * Serialize the component to its JSON contract node (docs/CONTRACT.md).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'placeholder' => $this->placeholder,
            'helperText' => $this->helperText,
            'default' => $this->default,
            'required' => $this->isRequired() ? true : null,
            'validation' => $this->validation !== [] ? $this->validation : null,
            'options' => $this->options !== null ? $this->serializeOptions() : null,
            'dependsOn' => $this->dependsOn,
            'whenTruthy' => $this->whenTruthy,
            'whenFalsy' => $this->whenFalsy,
            'disabled' => $this->isDisabled() ? true : null,
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
