<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Closure;

/**
 * Rich editor field (mirrors `Filament\Forms\Components\RichEditor`) — a
 * contenteditable HTML editor whose value is an HTML string. The client
 * renders a lightweight toolbar (bold / italic / underline / lists / link /
 * clear); the serialized value is raw HTML for the consumer's persistence
 * (sanitize server-side before rendering — the value is never trusted).
 *
 * Pure-data options: `placeholder()`, `toolbarButtons()`, `maxHeight()`.
 * Deferred: TipTap-based schema editor, images/attachments inside the body.
 */
class RichEditor extends Component
{
    protected ?string $placeholder = null;

    /** @var array<int, string>|null */
    protected ?array $toolbarButtons = null;

    protected ?int $maxHeight = null;

    public function getType(): string
    {
        return 'rich_editor';
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * @param  array<int, string>  $buttons
     */
    public function toolbarButtons(array $buttons): static
    {
        $this->toolbarButtons = $buttons;

        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getToolbarButtons(): ?array
    {
        return $this->toolbarButtons;
    }

    public function maxHeight(int $pixels): static
    {
        $this->maxHeight = max($pixels, 64);

        return $this;
    }

    public function getMaxHeight(): ?int
    {
        return $this->maxHeight;
    }

    /**
     * The value is an HTML string — the `string` rule is intrinsic, merged
     * with any declared rules (e.g. a conditional `required()`).
     *
     * @return array<int, string|object|Closure>
     */
    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();

        if (! in_array('string', $rules, true)) {
            $rules[] = 'string';
        }

        return $this->withBaseValidationRules($rules);
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'helperText' => $this->helperText,
            'hint' => $this->hint,
            'hintIcon' => $this->hintIcon,
            'required' => $this->isRequired() ? true : null,
            'disabled' => $this->isDisabled() ? true : null,
            'readOnly' => $this->isReadOnly() ? true : null,
            'dehydrated' => $this->isDehydrated() ? null : false,
            'toolbarButtons' => $this->toolbarButtons,
            'maxHeight' => $this->maxHeight,
        ]);
    }
}
