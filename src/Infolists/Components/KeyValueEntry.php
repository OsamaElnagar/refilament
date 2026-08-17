<?php

declare(strict_types=1);

namespace Refilament\Refilament\Infolists\Components;

/**
 * Key value entry — a read-only key/value definition list (slice 3.9 /
 * docs/PLAN_COLUMNS_INFOLISTS.md §2).
 *
 *     KeyValueEntry::make('meta')
 *         ->keyLabel('Setting')
 *         ->valueLabel('Value')
 *
 * The value is an associative `[key => value]` map or a list of
 * `{ key, value }` row objects; either is normalized server-side to the same
 * `{ key, value }` row shape the KeyValue *field* uses, so the read-only
 * renderer shares its look. Mirrors `Filament\Infolists\Components\KeyValueEntry`.
 */
class KeyValueEntry extends Entry
{
    protected ?string $keyLabel = null;

    protected ?string $valueLabel = null;

    public function getType(): string
    {
        return 'key_value_entry';
    }

    public function keyLabel(?string $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function getKeyLabel(): string
    {
        return $this->keyLabel ?? 'Key';
    }

    public function valueLabel(?string $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function getValueLabel(): string
    {
        return $this->valueLabel ?? 'Value';
    }

    /**
     * Message shown when the entry has no pairs. Mirrors Filament's
     * (deprecated) `emptyMessage()` alias of `placeholder()`.
     */
    public function emptyMessage(?string $message): static
    {
        return $this->placeholder($message);
    }

    public function toArray(?string $operation = null): array
    {
        $payload = parent::toArray($operation);

        $payload['value'] = $this->normalizeRows($payload['value'] ?? null);

        if ($this->keyLabel !== null) {
            $payload['keyLabel'] = $this->keyLabel;
        }

        if ($this->valueLabel !== null) {
            $payload['valueLabel'] = $this->valueLabel;
        }

        return $payload;
    }

    /**
     * Normalize the resolved value (an assoc map or a list of row objects) to
     * a list of `{ key, value }` rows.
     *
     * @return array<int, array{key: string, value: string}>
     */
    private function normalizeRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $row): ?array => is_array($row)
                    ? [
                        'key' => (string) ($row['key'] ?? ''),
                        'value' => self::scalarize($row['value'] ?? null),
                    ]
                    : null,
                $value,
            ), static fn (?array $row): bool => $row !== null));
        }

        return array_map(
            static fn (mixed $value, int|string $key): array => [
                'key' => (string) $key,
                'value' => self::scalarize($value),
            ],
            $value,
            array_keys($value),
        );
    }

    private static function scalarize(mixed $value): string
    {
        if ($value === null || is_scalar($value)) {
            return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}
