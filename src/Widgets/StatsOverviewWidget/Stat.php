<?php

declare(strict_types=1);

namespace Refilament\Refilament\Widgets\StatsOverviewWidget;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A stat card inside a StatsOverviewWidget (slice 3.1 — docs/ROADMAP.md
 * "3.1 Dashboard + StatsOverview widget").
 *
 * Mirrors Filament's `Stat` (filament-source/widgets/src/StatsOverviewWidget/
 * Stat.php) where it is pure data: a `label`, a `value`, and optional
 * `description` / `icon` / `color`. The value and each presentational option
 * may be a Closure evaluated at serialization time (mirroring Stat::getValue()
 * evaluating the constructor value) — e.g. `fn () => Post::count()`. Closures
 * never survive the wire; the shipped node is always a plain scalar.
 *
 * Deferred in v1: `chart()` / `chartColor()` (the HasChartData checksum +
 * broadcast machinery), `descriptionIcon` / `descriptionColor` /
 * `descriptionIconPosition`, polling (`CanPoll`). They are display surface,
 * not runtime-coupled config — simply not yet ported.
 */
class Stat
{
    protected mixed $value;

    protected ?string $label = null;

    protected bool $shouldTranslateLabel = false;

    /** @var string|Closure|null */
    protected mixed $description = null;

    /** @var string|Closure|null */
    protected mixed $icon = null;

    /** @var string|BackedEnum|Closure|null */
    protected mixed $color = null;

    final public function __construct(string $label, mixed $value)
    {
        $this->label = $label;
        $this->value = $value;
    }

    public static function make(string $label, mixed $value): static
    {
        return new static($label, $value);
    }

    /**
     * The stat's value. Accepts a scalar, an Htmlable, or a Closure evaluated
     * at serialization time (e.g. `Stat::make('Posts', fn (): int => Post::count())`).
     */
    public function value(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Treat the stat label as a translation key resolved through the app's
     * translator when the stat is serialized. Mirrors Filament's
     * `translateLabel()`; off by default so labels pass through verbatim.
     */
    public function translateLabel(bool $condition = true): static
    {
        $this->shouldTranslateLabel = $condition;

        return $this;
    }

    /**
     * A short supporting line under the value. Accepts a static string or a
     * Closure evaluated at serialization time (e.g. a percent change).
     */
    public function description(string|Closure $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * An icon key beside the value (heroicons by convention, e.g.
     * 'heroicon-o-document-text'). Accepts a static key or a Closure.
     */
    public function icon(string|Closure $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Color the value text. Accepts a static color name, a BackedEnum, or a
     * Closure.
     */
    public function color(string|BackedEnum|Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getLabel(): string
    {
        $label = (string) $this->label;

        return $this->shouldTranslateLabel ? __($label) : $label;
    }

    /**
     * The displayed value, resolved now. A Closure is evaluated (mirroring
     * Filament's getValue()); an Htmlable renders to a string; anything else
     * ships as-is.
     */
    public function getValue(): mixed
    {
        if ($this->value instanceof Closure) {
            return ($this->value)();
        }

        if ($this->value instanceof Htmlable) {
            return $this->value->toHtml();
        }

        return $this->value;
    }

    public function getDescription(): ?string
    {
        return $this->resolveToString($this->description);
    }

    public function getIcon(): ?string
    {
        return $this->resolveToString($this->icon);
    }

    public function getColor(): ?string
    {
        return $this->resolveToString($this->color);
    }

    /**
     * Serialize the stat node (docs/CONTRACT.md, "Widgets"). Omission
     * convention: `label` and `value` are always emitted; `description`,
     * `icon` and `color` only when they resolve to a non-null value.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'label' => $this->getLabel(),
            'value' => $this->getValue(),
        ];

        $description = $this->getDescription();

        if ($description !== null) {
            $payload['description'] = $description;
        }

        $icon = $this->getIcon();

        if ($icon !== null) {
            $payload['icon'] = $icon;
        }

        $color = $this->getColor();

        if ($color !== null) {
            $payload['color'] = $color;
        }

        return $payload;
    }

    private function resolveToString(mixed $value): ?string
    {
        if ($value instanceof Closure) {
            $value = $value();
        }

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        return (string) $value;
    }
}
