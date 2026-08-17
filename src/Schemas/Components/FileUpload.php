<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Closure;

/**
 * File upload field (mirrors `Filament\Forms\Components\FileUpload`) — a
 * file picker whose value is a stored path (or array of paths when
 * `multiple()`). Files upload through the panel's typed upload endpoint
 * (POST /refilament/upload) the moment the client picks them — the endpoint
 * validates the disk/directory and returns the stored path + public URL, and
 * the submit payload carries the path(s) for the consumer's own persistence
 * (a model cast, or the submitUsing() handler).
 *
 * Pure-data options mirroring Filament: `disk()`, `directory()`,
 * `acceptedFileTypes()`, `maxSize()` (KB), `multiple()`, `imagePreview()`
 * (thumbnails for image types) and `visibility()` ('public' | 'private').
 * Validation: the value is `string` (single) or `array` (multiple).
 */
class FileUpload extends Component
{
    protected ?string $disk = null;

    protected ?string $directory = null;

    /** @var array<int, string>|null */
    protected ?array $acceptedFileTypes = null;

    protected ?int $maxSize = null;

    protected bool $multiple = false;

    protected bool $imagePreview = true;

    protected ?string $visibility = null;

    public function getType(): string
    {
        return 'file_upload';
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function directory(?string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    /**
     * @param  array<int, string>  $types
     */
    public function acceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getAcceptedFileTypes(): ?array
    {
        return $this->acceptedFileTypes;
    }

    public function maxSize(int $kilobytes): static
    {
        $this->maxSize = max($kilobytes, 1);

        return $this;
    }

    public function getMaxSize(): ?int
    {
        return $this->maxSize;
    }

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function imagePreview(bool $condition = true): static
    {
        $this->imagePreview = $condition;

        return $this;
    }

    public function hasImagePreview(): bool
    {
        return $this->imagePreview;
    }

    public function visibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    /**
     * The value is a stored path (string) or an array of paths when
     * multiple() — the matching intrinsic rule, merged with any declared
     * rules (e.g. a conditional `required()`).
     *
     * @return array<int, string|object|Closure>
     */
    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();

        $intrinsic = $this->isMultiple() ? 'array' : 'string';

        if (! in_array($intrinsic, $rules, true)) {
            $rules[] = $intrinsic;
        }

        return $this->withBaseValidationRules($rules);
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            'type' => $this->getType(),
            'name' => $this->getName(),
            'label' => $this->label,
            'helperText' => $this->helperText,
            'hint' => $this->hint,
            'hintIcon' => $this->hintIcon,
            'required' => $this->isRequired() ? true : null,
            'disabled' => $this->isDisabled() ? true : null,
            'readOnly' => $this->isReadOnly() ? true : null,
            'dehydrated' => $this->isDehydrated() ? null : false,
            'disk' => $this->disk,
            'directory' => $this->directory,
            'acceptedFileTypes' => $this->acceptedFileTypes,
            'maxSize' => $this->maxSize,
            'multiple' => $this->multiple ? true : null,
            'imagePreview' => $this->imagePreview ? null : false,
            'visibility' => $this->visibility,
        ]);
    }
}
