<?php

declare(strict_types=1);

namespace Refilament\Refilament\Tables\Columns;

use Refilament\Refilament\Tables\Column;

/**
 * Image column — renders the cell value as one or more thumbnails (mirrors
 * `Filament\Tables\Columns\ImageColumn`). The state is a URL string or an
 * array of URLs, serialized per record as `{ images, remaining? }`.
 *
 * Pure-data options mirroring Filament: `imageSize()` (square thumbnails),
 * `circular()` / `square()`, `stacked()` (overlapping avatars) with `ring()`
 * and `overlap()`, `limit()` (max thumbnails) with `limitedRemainingText()`
 * (shows the overflow count), `alt()`, and `defaultImageUrl()` (rendered when
 * the state is blank).
 *
 * Deferred for v1: disk-backed storage (`->disk()` / `->visibility()` /
 * temporary URLs) — the state is treated as a URL or path passed through as-is.
 *
 * @method static \Refilament\Refilament\Tables\Columns\ImageColumn sortable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\ImageColumn searchable(bool $condition = true)
 * @method static \Refilament\Refilament\Tables\Columns\ImageColumn toggleable(bool $condition = true)
 */
class ImageColumn extends Column
{
    protected int|string|null $imageSize = null;

    protected bool $isCircular = false;

    protected bool $isSquare = false;

    protected bool $isStacked = false;

    protected ?int $ring = null;

    protected ?int $overlap = null;

    protected ?int $limit = null;

    protected bool $hasLimitedRemainingText = false;

    protected ?string $alt = null;

    protected ?string $defaultImageUrl = null;

    public function imageSize(int|string|null $size): static
    {
        $this->imageSize = $size;

        return $this;
    }

    public function getImageSize(): int|string|null
    {
        return $this->imageSize;
    }

    public function circular(bool $condition = true): static
    {
        $this->isCircular = $condition;

        return $this;
    }

    public function isCircular(): bool
    {
        return $this->isCircular;
    }

    public function square(bool $condition = true): static
    {
        $this->isSquare = $condition;

        return $this;
    }

    public function isSquare(): bool
    {
        return $this->isSquare;
    }

    public function stacked(bool $condition = true): static
    {
        $this->isStacked = $condition;

        return $this;
    }

    public function isStacked(): bool
    {
        return $this->isStacked;
    }

    public function ring(?int $ring): static
    {
        $this->ring = $ring;

        return $this;
    }

    public function getRing(): ?int
    {
        return $this->ring;
    }

    public function overlap(?int $overlap): static
    {
        $this->overlap = $overlap;

        return $this;
    }

    public function getOverlap(): ?int
    {
        return $this->overlap;
    }

    public function limit(?int $length = 3, ?string $end = null): static
    {
        // Signature-compatible with the base text-limit `limit()`; for an
        // image column the length caps the number of thumbnails (Filament's
        // `ImageColumn::limit()`), not a character count.
        $this->limit = $length;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function limitedRemainingText(bool $condition = true): static
    {
        $this->hasLimitedRemainingText = $condition;

        return $this;
    }

    public function hasLimitedRemainingText(): bool
    {
        return $this->hasLimitedRemainingText;
    }

    public function alt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function defaultImageUrl(?string $url): static
    {
        $this->defaultImageUrl = $url;

        return $this;
    }

    public function getDefaultImageUrl(): ?string
    {
        return $this->defaultImageUrl;
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['kind'] = 'image';

        if ($this->imageSize !== null) {
            $payload['size'] = $this->imageSize;
        }

        if ($this->isCircular()) {
            $payload['circular'] = true;
        }

        if ($this->isSquare()) {
            $payload['square'] = true;
        }

        if ($this->isStacked()) {
            $payload['stacked'] = true;
        }

        if ($this->ring !== null) {
            $payload['ring'] = $this->ring;
        }

        if ($this->overlap !== null) {
            $payload['overlap'] = $this->overlap;
        }

        if ($this->limit !== null) {
            $payload['limit'] = $this->limit;
        }

        if ($this->hasLimitedRemainingText()) {
            $payload['limitedRemainingText'] = true;
        }

        if ($this->defaultImageUrl !== null) {
            $payload['defaultImageUrl'] = $this->defaultImageUrl;
        }

        return $payload;
    }

    public function serializeCell(mixed $record): mixed
    {
        $state = $this->getStateFor($record);
        $blank = $state === null || $state === '' || $state === [];

        if ($blank) {
            if ($this->defaultImageUrl === null) {
                return null;
            }

            $images = [$this->defaultImageUrl];
        } else {
            $images = is_array($state)
                ? array_values(array_filter($state, static fn (mixed $url): bool => $url !== null && $url !== ''))
                : [(string) $state];
        }

        if ($images === []) {
            return null;
        }

        $limit = $this->limit;
        $remaining = ($limit !== null && count($images) > $limit) ? count($images) - $limit : 0;

        if ($remaining > 0) {
            $images = array_slice($images, 0, $limit);
        }

        $cell = ['value' => implode(', ', $images), 'images' => $images];

        if ($remaining > 0 && $this->hasLimitedRemainingText()) {
            $cell['remaining'] = $remaining;
        }

        return $cell;
    }
}
