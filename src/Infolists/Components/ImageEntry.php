<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

/**
 * Image entry — a record's image URL(s) rendered as thumbnails (slice 3.9 /
 * docs/PLAN_COLUMNS_INFOLISTS.md §2).
 *
 *     ImageEntry::make('cover_photo')
 *         ->size(64)
 *         ->circular()
 *         ->stacked()
 *         ->limit(3)
 *
 * The value is a single image URL or a list of them; each URL renders as a
 * thumbnail, optionally circular/square, stacked (avatars with a ring) and
 * limited to `limit()` images with an overflow count. URLs only for v1 —
 * local disk-backed files are deferred (mirrors Filament's `->disk()`).
 * Mirrors `Filament\Infolists\Components\ImageEntry`.
 */
class ImageEntry extends Entry
{
    protected ?int $size = null;

    protected bool $isCircular = false;

    protected bool $isSquare = false;

    protected bool $isStacked = false;

    protected ?int $ring = null;

    protected ?int $limit = null;

    public function getType(): string
    {
        return 'image_entry';
    }

    /**
     * Square thumbnail size in pixels (applies to every image).
     */
    public function size(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function circular(bool $condition = true): static
    {
        $this->isCircular = $condition;

        return $this;
    }

    public function square(bool $condition = true): static
    {
        $this->isSquare = $condition;

        return $this;
    }

    public function stacked(bool $condition = true): static
    {
        $this->isStacked = $condition;

        return $this;
    }

    /**
     * Ring thickness (in px) around each thumbnail when stacked.
     */
    public function ring(int $ring): static
    {
        $this->ring = $ring;

        return $this;
    }

    /**
     * Render at most this many thumbnails; the rest collapse into a "+N" count.
     *
     * Keeps the parent `Entry::limit()` signature (the text-truncation
     * formatter is never applied to an image value) but reinterprets the
     * length as an image-count cap: `->limit(3)` shows up to three
     * thumbnails.
     */
    public function limit(int $length = 100, string $end = '...'): static
    {
        $this->limit = $length;

        return $this;
    }

    public function toArray(?string $operation = null): array
    {
        $payload = parent::toArray($operation);

        $payload['images'] = $this->normalizeImages($payload['value'] ?? null);

        if ($this->size !== null) {
            $payload['size'] = $this->size;
        }

        if ($this->isCircular) {
            $payload['circular'] = true;
        }

        if ($this->isSquare) {
            $payload['square'] = true;
        }

        if ($this->isStacked) {
            $payload['stacked'] = true;
        }

        if ($this->ring !== null) {
            $payload['ring'] = $this->ring;
        }

        if ($this->limit !== null) {
            $payload['limit'] = $this->limit;
        }

        return $payload;
    }

    /**
     * Normalize the resolved value (a single URL or a list of them) to a list
     * of image URL strings.
     *
     * @return array<int, string>
     */
    private function normalizeImages(mixed $value): array
    {
        $images = is_array($value) ? $value : (filled($value) ? [$value] : []);

        return array_values(array_filter($images, static fn (mixed $url): bool => is_string($url)));
    }
}
