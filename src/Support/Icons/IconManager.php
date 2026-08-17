<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Icons;

use BackedEnum;
use Illuminate\Support\Arr;

/**
 * The registry behind the `RefilamentIcon` facade (mirrors Filament's
 * `IconManager`). It maps icon aliases to their resolved icon — a canonical
 * key string or a `ScalableIcon`-style `BackedEnum` — so components can
 * reference icons by a stable alias and let consumers override them globally.
 *
 * `register()` merges across calls (later keys win) and `resolve()` returns
 * the first match in an alias list, mirroring Filament's behavior.
 */
class IconManager
{
    /**
     * @var array<string, string | BackedEnum>
     */
    protected array $icons = [];

    /**
     * @param  array<string, string | BackedEnum>  $icons
     */
    public function register(array $icons): void
    {
        $this->icons = [
            ...$this->icons,
            ...$icons,
        ];
    }

    /**
     * @param  string|array<string>  $alias
     */
    public function resolve(string|array $alias): string|BackedEnum|null
    {
        foreach (Arr::wrap($alias) as $alias) {
            if (isset($this->icons[$alias])) {
                return $this->icons[$alias];
            }
        }

        return null;
    }

    /**
     * Collapse a `BackedEnum` icon to its canonical key string. Builders whose
     * icon is a fixed value (not a per-record closure) store the canonical key
     * directly, so their `?string` getters and JSON payloads stay untouched.
     */
    public static function normalize(string|BackedEnum|null $icon): ?string
    {
        if ($icon === null) {
            return null;
        }

        if ($icon instanceof BackedEnum) {
            return (string) $icon->value;
        }

        return $icon;
    }
}
