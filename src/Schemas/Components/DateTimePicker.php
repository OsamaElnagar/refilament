<?php

declare(strict_types=1);

namespace Refilament\Refilament\Schemas\Components;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Date & time picker field.
 *
 * Mirrors Filament's `DateTimePicker` config API where it is pure data. The
 * picker ships as a client-side contract node: the React runtime renders the
 * calendar + time inputs from the serialized `inputType` and formats state via
 * `format` / `displayFormat`. State is a plain string in `format` (e.g.
 * `Y-m-d H:i:s`), so no Carbon cast survives the wire.
 *
 * As with the other schemas fields, configuration is imperative (not
 * re-evaluated per render): closures are deferred for v1, so `minDate()` /
 * `maxDate()` append their `after_or_equal` / `before_or_equal` validation
 * rules at config time from the given value.
 *
 * Deferred for v1: closure-config, native `<input type="date">` rendering,
 * affixes (prefix/suffix icons), datalist options, read-only, extra trigger
 * attributes, locale-aware defaults overrides.
 */
class DateTimePicker extends Component
{
    protected bool $hasDate = true;

    protected bool $hasTime = true;

    protected bool $hasSeconds = true;

    protected ?string $format = null;

    protected ?string $displayFormat = null;

    protected CarbonInterface|string|null $maxDate = null;

    protected CarbonInterface|string|null $minDate = null;

    /**
     * @var array<DateTimeInterface|string>
     */
    protected array $disabledDates = [];

    protected ?int $firstDayOfWeek = null;

    protected ?int $hoursStep = null;

    protected ?int $minutesStep = null;

    protected ?int $secondsStep = null;

    protected ?string $timezone = null;

    protected ?string $locale = null;

    protected bool $shouldCloseOnDateSelection = false;

    public function getType(): string
    {
        return 'date_time_picker';
    }

    /**
     * Toggle the date portion. Mirrors Filament's `date()`. Disabling it
     * yields a time-only picker (`TimePicker`).
     */
    public function date(bool $condition = true): static
    {
        $this->hasDate = $condition;

        return $this;
    }

    /**
     * Toggle the time portion. Mirrors Filament's `time()`. Disabling it
     * yields a date-only picker (`DatePicker`).
     */
    public function time(bool $condition = true): static
    {
        $this->hasTime = $condition;

        return $this;
    }

    /**
     * Toggle the seconds portion of the time inputs.
     */
    public function seconds(bool $condition = true): static
    {
        $this->hasSeconds = $condition;

        return $this;
    }

    /**
     * The internal state format (PHP `date()` tokens) the value is stored in,
     * e.g. `Y-m-d` or `Y-m-d H:i:s`. Defaults derived from the toggles.
     */
    public function format(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * The day.js display format the React runtime renders the value with,
     * e.g. `M j, Y`. Defaults derived from the toggles.
     */
    public function displayFormat(?string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    public function maxDate(CarbonInterface|string|null $date): static
    {
        $this->maxDate = $date;

        if ($date !== null) {
            $this->pushValidationRules(['before_or_equal:'.$this->dateForRule($date)]);
        }

        return $this;
    }

    public function minDate(CarbonInterface|string|null $date): static
    {
        $this->minDate = $date;

        if ($date !== null) {
            $this->pushValidationRules(['after_or_equal:'.$this->dateForRule($date)]);
        }

        return $this;
    }

    /**
     * @param  array<DateTimeInterface|string>  $dates
     */
    public function disabledDates(array $dates): static
    {
        $this->disabledDates = $dates;

        return $this;
    }

    /**
     * The first day of the week shown in the calendar header, where 1 is
     * Monday and 7 is Sunday. Out-of-range values reset to the default.
     */
    public function firstDayOfWeek(?int $day): static
    {
        if ($day !== null && ($day < 1 || $day > 7)) {
            $day = null;
        }

        $this->firstDayOfWeek = $day;

        return $this;
    }

    public function weekStartsOnMonday(): static
    {
        return $this->firstDayOfWeek(1);
    }

    public function weekStartsOnSunday(): static
    {
        return $this->firstDayOfWeek(7);
    }

    public function hoursStep(?int $hoursStep): static
    {
        $this->hoursStep = $hoursStep;

        return $this;
    }

    public function minutesStep(?int $minutesStep): static
    {
        $this->minutesStep = $minutesStep;

        return $this;
    }

    public function secondsStep(?int $secondsStep): static
    {
        $this->secondsStep = $secondsStep;

        return $this;
    }

    public function timezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function locale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Close the picker panel as soon as a date is selected.
     */
    public function closeOnDateSelection(bool $condition = true): static
    {
        $this->shouldCloseOnDateSelection = $condition;

        return $this;
    }

    public function hasDate(): bool
    {
        return $this->hasDate;
    }

    public function hasTime(): bool
    {
        return $this->hasTime;
    }

    public function hasSeconds(): bool
    {
        return $this->hasSeconds;
    }

    public function getFormat(): string
    {
        if ($this->format !== null) {
            return $this->format;
        }

        $format = $this->hasDate() ? 'Y-m-d' : '';

        if (! $this->hasTime()) {
            return $format;
        }

        $format = $format === '' ? 'H:i' : "{$format} H:i";

        if (! $this->hasSeconds()) {
            return $format;
        }

        return "{$format}:s";
    }

    public function getDisplayFormat(): string
    {
        $format = $this->displayFormat;

        if ($format !== null) {
            return $format;
        }

        if (! $this->hasTime()) {
            return 'M j, Y';
        }

        if (! $this->hasDate()) {
            return $this->hasSeconds() ? 'H:i:s' : 'H:i';
        }

        return $this->hasSeconds() ? 'M j, Y H:i:s' : 'M j, Y H:i';
    }

    public function getMaxDate(): CarbonInterface|string|null
    {
        return $this->maxDate;
    }

    public function getMinDate(): CarbonInterface|string|null
    {
        return $this->minDate;
    }

    /**
     * @return array<DateTimeInterface|string>
     */
    public function getDisabledDates(): array
    {
        return $this->disabledDates;
    }

    public function getFirstDayOfWeek(): int
    {
        return $this->firstDayOfWeek ?? 1;
    }

    public function getHoursStep(): int
    {
        return $this->hoursStep ?? 1;
    }

    public function getMinutesStep(): int
    {
        return $this->minutesStep ?? 1;
    }

    public function getSecondsStep(): int
    {
        return $this->secondsStep ?? 1;
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? date_default_timezone_get();
    }

    public function getLocale(): string
    {
        return $this->locale ?? 'en';
    }

    public function shouldCloseOnDateSelection(): bool
    {
        return $this->shouldCloseOnDateSelection;
    }

    /**
     * The HTML input type the picker emulates: `date`, `time` or
     * `datetime-local`. Serialized so the React runtime knows which portions
     * to render.
     */
    public function getInputType(): string
    {
        if (! $this->hasDate()) {
            return 'time';
        }

        if (! $this->hasTime()) {
            return 'date';
        }

        return 'datetime-local';
    }

    public function toArray(?string $operation = null): array
    {
        return $this->filterNullValues([
            ...parent::toArray($operation),
            'inputType' => $this->getInputType(),
            'format' => $this->getFormat(),
            'displayFormat' => $this->getDisplayFormat(),
            'maxDate' => $this->dateForSerialization($this->maxDate),
            'minDate' => $this->dateForSerialization($this->minDate),
            'disabledDates' => $this->disabledDates !== []
                ? array_map(fn (DateTimeInterface|string $date): string => $this->dateForSerialization($date), $this->disabledDates)
                : null,
            'firstDayOfWeek' => $this->firstDayOfWeek !== null ? $this->getFirstDayOfWeek() : null,
            'hoursStep' => $this->hoursStep,
            'minutesStep' => $this->minutesStep,
            'secondsStep' => $this->secondsStep,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'closeOnDateSelection' => $this->shouldCloseOnDateSelection() ? true : null,
        ]);
    }

    private function dateForSerialization(DateTimeInterface|string|null $date): ?string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        return $date;
    }

    private function dateForRule(CarbonInterface|string $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : $date;
    }
}
