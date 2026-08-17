<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use LogicException;

/**
 * Text input field (slice 2).
 *
 * Mirrors Filament's TextInput config API where it is pure data. The HTML
 * input type is derived and serialized as `inputType`; unambiguous Laravel
 * validation rules (email, url, numeric, integer, current_password) are
 * appended server-side. minValue/maxValue and the tel regex are serialized as
 * client-side hints only in v1 (no rules appended).
 *
 * Note: passing `false` to a condition (e.g. `email(false)`) does not revoke
 * previously-set config or rules — configuration is imperative, not
 * re-evaluated per render like Filament's closures.
 *
 * Deferred for v1: mask, affixes (prefix/suffix), datalist options, inputMode,
 * minLength, readOnly, autocomplete, autocapitalize, state casts.
 */
class TextInput extends Component
{
    protected bool $isEmail = false;

    protected bool $isNumeric = false;

    protected bool $isPassword = false;

    protected bool $isTel = false;

    protected bool $isUrl = false;

    protected bool $isRevealable = false;

    protected bool $isCopyable = false;

    protected int|string|float|null $minValue = null;

    protected int|string|float|null $maxValue = null;

    protected int|string|float|null $step = null;

    protected ?string $telRegex = null;

    protected ?string $type = null;

    protected ?string $copyMessage = null;

    protected ?string $autocomplete = null;

    /**
     * Set the HTML autocomplete attribute for the input.
     */
    public function autocomplete(string $value): static
    {
        $this->autocomplete = $value;

        return $this;
    }

    /**
     * A client-side arithmetic expression (slice C3) whose result this field
     * displays and submits — the honest counterpart to Filament's reactive
     * `afterStateUpdated(Get, Set)` totals. Serialized as data; the React
     * runtime evaluates it against live form values (no round trip). The DSL
     * is deliberately tiny: numbers, + - * / % and parentheses, referencing
     * sibling fields by name. Unresolvable inputs produce a null result.
     */
    protected ?string $computedExpression = null;

    public function getType(): string
    {
        return 'text_input';
    }

    public function currentPassword(bool $condition = true): static
    {
        if ($condition) {
            $this->pushValidationRules(['current_password']);
        }

        return $this;
    }

    public function email(bool $condition = true): static
    {
        $this->isEmail = $condition;

        if ($condition) {
            $this->pushValidationRules(['email']);
        }

        return $this;
    }

    public function integer(bool $condition = true): static
    {
        $this->isNumeric = $condition;

        if ($condition) {
            $this->pushValidationRules(['integer']);
        }

        return $this;
    }

    public function numeric(bool $condition = true): static
    {
        $this->isNumeric = $condition;

        if ($condition) {
            $this->pushValidationRules(['numeric']);
        }

        return $this;
    }

    public function password(bool $condition = true): static
    {
        $this->isPassword = $condition;

        return $this;
    }

    public function revealable(bool $condition = true): static
    {
        $this->isRevealable = $condition;

        return $this;
    }

    public function copyable(bool $condition = true, ?string $copyMessage = null): static
    {
        $this->isCopyable = $condition;
        $this->copyMessage = $copyMessage;

        return $this;
    }

    public function tel(bool $condition = true): static
    {
        $this->isTel = $condition;

        return $this;
    }

    public function url(bool $condition = true): static
    {
        $this->isUrl = $condition;

        if ($condition) {
            $this->pushValidationRules(['url']);
        }

        return $this;
    }

    public function telRegex(?string $regex): static
    {
        $this->telRegex = $regex;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function minValue(int|string|float $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function maxValue(int|string|float $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    /**
     * The step for numeric inputs (slice 1.5) — mirrors Filament's
     * NumberInput::step(). Serialized as a client-side hint only.
     */
    public function step(int|string|float $value): static
    {
        $this->step = $value;

        return $this;
    }

    /**
     * Compute this field's value client-side from an arithmetic expression
     * over sibling fields (slice C3) — the Ahram invoice idiom
     * (subtotal = quantity × unit_price; VAT = subtotal × 14%; total =
     * subtotal + VAT) without a server round trip. Referenced fields must
     * appear earlier in the schema so the runtime can chain the computation
     * in declaration order.
     */
    public function computed(string $expression): static
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new LogicException('computed() requires a non-empty expression.');
        }

        $this->computedExpression = $expression;

        return $this;
    }

    public function getComputedExpression(): ?string
    {
        return $this->computedExpression;
    }

    public function isEmail(): bool
    {
        return $this->isEmail;
    }

    public function isNumeric(): bool
    {
        return $this->isNumeric;
    }

    public function isPassword(): bool
    {
        return $this->isPassword;
    }

    public function isTel(): bool
    {
        return $this->isTel;
    }

    public function isUrl(): bool
    {
        return $this->isUrl;
    }

    public function isRevealable(): bool
    {
        return $this->isRevealable;
    }

    public function isCopyable(): bool
    {
        return $this->isCopyable;
    }

    public function getMinValue(): int|string|float|null
    {
        return $this->minValue;
    }

    public function getMaxValue(): int|string|float|null
    {
        return $this->maxValue;
    }

    public function getStep(): int|string|float|null
    {
        return $this->step;
    }

    public function getTelRegex(): string
    {
        return $this->telRegex ?? '/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/';
    }

    /**
     * The HTML input type, mirroring Filament's resolution order:
     * explicit type > email > numeric > password > tel > url > text.
     */
    public function getInputType(): string
    {
        if (filled($this->type)) {
            return $this->type;
        }

        return match (true) {
            $this->isEmail() => 'email',
            $this->isNumeric() => 'number',
            $this->isPassword() => 'password',
            $this->isTel() => 'tel',
            $this->isUrl() => 'url',
            default => 'text',
        };
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'inputType' => $this->getInputType() !== 'text' ? $this->getInputType() : null,
            'minValue' => $this->minValue,
            'maxValue' => $this->maxValue,
            'step' => $this->step,
            'revealable' => $this->isRevealable() ? true : null,
            'copyable' => $this->isCopyable() ? true : null,
            'copyMessage' => $this->copyMessage,
            'telRegex' => $this->telRegex,
            'computed' => $this->computedExpression,
        ]);
    }
}
