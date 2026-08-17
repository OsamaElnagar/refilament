<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

/**
 * Code entry — a read-only `<pre><code>` block (slice 3.9 /
 * docs/PLAN_COLUMNS_INFOLISTS.md §2).
 *
 *     CodeEntry::make('body')
 *         ->language('html')
 *         ->lineNumbers()
 *         ->copyable()
 *
 * Plain syntax presentation for v1 — `language()` is a plain-text label and
 * syntax highlighting (Prism/Shiki) is deferred. The value is a string (or an
 * array, pretty-printed as JSON server-side). A `copyable()` block copies the
 * value on click. Mirrors `Filament\Infolists\Components\CodeEntry`, minus the
 * Phiki grammar/theme machinery.
 */
class CodeEntry extends Entry
{
    protected ?string $language = null;

    protected bool $hasLineNumbers = false;

    protected bool $isCopyable = false;

    public function getType(): string
    {
        return 'code_entry';
    }

    /**
     * A plain-language label for the block (e.g. "html", "json"). No
     * highlighting is applied in v1.
     */
    public function language(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function lineNumbers(bool $condition = true): static
    {
        $this->hasLineNumbers = $condition;

        return $this;
    }

    public function copyable(bool $condition = true): static
    {
        $this->isCopyable = $condition;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->isCopyable;
    }

    public function toArray(?string $operation = null): array
    {
        $payload = parent::toArray($operation);

        // Pretty-print array values as JSON so the block shows readable code.
        $code = $payload['value'] ?? null;

        if (is_array($code)) {
            $payload['value'] = json_encode($code, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($this->language !== null) {
            $payload['language'] = $this->language;
        }

        if ($this->hasLineNumbers) {
            $payload['lineNumbers'] = true;
        }

        if ($this->isCopyable) {
            $payload['copyable'] = true;
        }

        return $payload;
    }
}
