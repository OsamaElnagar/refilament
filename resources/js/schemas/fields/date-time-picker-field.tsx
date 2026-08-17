import { useEffect, useMemo, useState } from 'react';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import {
    addMonths,
    dayDate,
    daysInMonth,
    formatDisplay,
    isSameDay,
    isToday,
    leadingBlankCount,
    monthLabel,
    parseState,
    serializeState,
} from '@/schemas/date-utils';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

const WEEKDAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function DateTimePickerField({ node, value, error, onChange, formValues }: FieldProps) {
    const format = node.format ?? 'Y-m-d H:i:s';
    const displayFormat = node.displayFormat ?? 'M j, Y H:i';
    const firstDayOfWeek = node.firstDayOfWeek ?? 1;
    const inputType = node.inputType ?? 'datetime-local';

    const hasDate = inputType !== 'time';
    const hasTime = inputType !== 'date';
    const hasSeconds = format.includes('s');

    const parsed = useMemo(() => parseState(value as string | undefined, format), [value, format]);
    const baseDate = parsed ?? new Date();

    const [open, setOpen] = useState(false);
    const [focused, setFocused] = useState<Date>(() => parsed ?? new Date());

    useEffect(() => {
        if (parsed) {
            setFocused(new Date(parsed.getFullYear(), parsed.getMonth(), 1));
        }
    }, [value]);

    const disabled = node.disabled ?? node.readOnly ?? false;
    const minDate = node.minDate ? parseState(node.minDate, format) : null;
    const maxDate = node.maxDate ? parseState(node.maxDate, format) : null;
    const disabledDates = (node.disabledDates ?? []).map((date) => parseState(date, format)).filter(Boolean) as Date[];

    const displayText = value ? formatDisplay(parsed, displayFormat) : '';

    const isDisabledDay = (day: number): boolean => {
        const date = dayDate(focused, day);

        if (minDate && date < startOfDay(minDate)) {
            return true;
        }

        if (maxDate && date > endOfDay(maxDate)) {
            return true;
        }

        return disabledDates.some((disabled) => isSameDay(date, disabled));
    };

    const selectDay = (day: number) => {
        const date = new Date(
            focused.getFullYear(),
            focused.getMonth(),
            day,
            baseDate.getHours(),
            baseDate.getMinutes(),
            baseDate.getSeconds(),
        );

        onChange?.(serializeState(date, format));

        if (node.closeOnDateSelection) {
            setOpen(false);
        }
    };

    const updateTime = (part: 'hours' | 'minutes' | 'seconds', nextValue: string) => {
        const parsedNext = Number(nextValue);
        const date = new Date(baseDate);

        if (part === 'hours') {
            date.setHours(parsedNext);
        } else if (part === 'minutes') {
            date.setMinutes(parsedNext);
        } else {
            date.setSeconds(parsedNext);
        }

        onChange?.(serializeState(date, format));
    };

    const blanks = leadingBlankCount(focused, firstDayOfWeek);
    const totalDays = daysInMonth(focused);

    return (
        <div>
            <FieldHeader node={node} formValues={formValues} labelId={node.name} />

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <Button
                            type="button"
                            variant="outline"
                            disabled={disabled}
                            aria-invalid={error ? true : undefined}
                            className={cn(
                                'w-full justify-start font-normal',
                                !displayText && 'text-muted-foreground',
                                error && 'border-destructive focus-visible:ring-destructive/30',
                            )}
                        >
                            <CalendarIcon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span className="truncate">{displayText || node.placeholder || 'Pick a date'}</span>
                        </Button>
                    }
                />

                <PopoverContent className="w-auto p-3" align="start">
                    <div className="space-y-3">
                        {hasDate ? (
                            <>
                                <div className="flex items-center justify-between">
                                    <Button type="button" variant="ghost" size="icon" className="size-7" onClick={() => setFocused(addMonths(focused, -1))} aria-label="Previous month">
                                        <ChevronLeft className="size-4" />
                                    </Button>
                                    <span className="text-sm font-medium">{monthLabel(focused)}</span>
                                    <Button type="button" variant="ghost" size="icon" className="size-7" onClick={() => setFocused(addMonths(focused, 1))} aria-label="Next month">
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>

                                <div className="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                                    {WEEKDAY_LABELS.map((label, index) => (
                                        <div key={index} className="py-1">
                                            {label}
                                        </div>
                                    ))}
                                </div>

                                <div role="grid" className="grid grid-cols-7 gap-1">
                                    {Array.from({ length: blanks }).map((_, index) => (
                                        <div key={`blank-${index}`} />
                                    ))}
                                    {Array.from({ length: totalDays }).map((_, index) => {
                                        const day = index + 1;
                                        const date = dayDate(focused, day);
                                        const dayDisabled = isDisabledDay(day);
                                        const selected = parsed ? isSameDay(date, parsed) : false;

                                        return (
                                            <button
                                                key={day}
                                                type="button"
                                                disabled={dayDisabled}
                                                onClick={() => selectDay(day)}
                                                className={cn(
                                                    'size-7 rounded-md text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                                    selected && 'bg-primary text-primary-foreground font-medium',
                                                    !selected && isToday(date) && 'bg-accent',
                                                    !selected && !isToday(date) && 'hover:bg-accent',
                                                    dayDisabled && 'cursor-not-allowed text-muted-foreground/40 hover:bg-transparent',
                                                )}
                                            >
                                                {day}
                                            </button>
                                        );
                                    })}
                                </div>
                            </>
                        ) : null}

                        {hasTime ? (
                            <div className="flex items-center justify-center gap-2 border-t pt-3">
                                <div className="flex items-center gap-1">
                                    <Input
                                        type="number"
                                        min={0}
                                        max={23}
                                        value={baseDate.getHours()}
                                        onChange={(event) => updateTime('hours', event.target.value)}
                                        className="w-14 px-2 text-center"
                                        aria-label="Hour"
                                    />
                                    <span className="text-muted-foreground">:</span>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={59}
                                        value={baseDate.getMinutes()}
                                        onChange={(event) => updateTime('minutes', event.target.value)}
                                        className="w-14 px-2 text-center"
                                        aria-label="Minute"
                                    />
                                    {hasSeconds ? (
                                        <>
                                            <span className="text-muted-foreground">:</span>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={59}
                                                value={baseDate.getSeconds()}
                                                onChange={(event) => updateTime('seconds', event.target.value)}
                                                className="w-14 px-2 text-center"
                                                aria-label="Second"
                                            />
                                        </>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </PopoverContent>
            </Popover>

            {error ? <p className="mt-0.5 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function endOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59, 999);
}