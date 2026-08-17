<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Closure;
use Refilament\Refilament\Tables\Column;

/**
 * Text input column - an inline-editable text field (mirrors
 * `Filament\Tables\Columns\TextInputColumn`). Typing a value and pressing
 * Enter (or blurring) posts it through the record-column update endpoint (a
 * stateless request/response). The cell state is the raw attribute value.
 *
 * @method static \Refilament\Refilament\Tables\Columns\TextInputColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\TextInputColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\TextInputColumn toggleable(bool $condition = true)
 */
class TextInputColumn extends Column
{
    protected string|Closure|null $type = null;

    protected string|Closure|null $inputMode = null;

    protected int|float|string|Closure|null $step = null;

    protected ?int $maxLength = null;

    public function configure(): static
    {
        return $this->editable();
    }

    public function type(string|Closure|null $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->evaluate($this->type);
    }

    public function inputMode(string|Closure|null $inputMode): static
    {
        $this->inputMode = $inputMode;

        return $this;
    }

    public function getInputMode(): ?string
    {
        return $this->evaluate($this->inputMode);
    }

    public function step(int|float|string|Closure|null $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getStep(): int|float|string|null
    {
        return $this->evaluate($this->step);
    }

    /**
     * Cap the input length. Ships a `maxlength` attribute to the client and
     * appends Laravel's `max:{n}` rule to the column's server-side rules.
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        $this->rules([...$this->getEditRules(), "max:{$length}"]);

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = 'text';

        if (($type = $this->getType()) !== null) {
            $payload['type'] = $type;
        }

        if (($inputMode = $this->getInputMode()) !== null) {
            $payload['inputMode'] = $inputMode;
        }

        if (($step = $this->getStep()) !== null) {
            $payload['step'] = $step;
        }

        if ($this->maxLength !== null) {
            $payload['maxLength'] = $this->maxLength;
        }

        return $payload;
    }
}
