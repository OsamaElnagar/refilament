import { useMemo, useState } from 'react';
import { Check } from 'lucide-react';

import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import {
    hslToRgb,
    parseColor,
    PRESET_COLORS,
    rgbToHsl,
    toFormat,
    type ColorFormat,
    type RGBA,
} from '@/schemas/color-utils';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

export default function ColorPickerField({ node, value, error, onChange, formValues }: FieldProps) {
    const format = (node.format ?? 'hex') as ColorFormat;
    const disabled = node.disabled ?? node.readOnly ?? false;

    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<string>((value as string) ?? '');

    const parsed = useMemo(() => parseColor((value as string) ?? '') ?? { r: 255, g: 0, b: 0, a: 1 }, [value]);

    const [hue, sat, light] = rgbToHsl(parsed.r, parsed.g, parsed.b);
    const hasAlpha = format === 'rgba';

    const pick = (color: RGBA) => {
        const formatted = toFormat(color, format);
        setDraft(formatted);
        onChange?.(formatted);
    };

    const setFromHueSatLight = (nextHue: number, nextSat: number, nextLight: number) => {
        pick({ ...hslToRgb(nextHue, nextSat, nextLight), a: parsed.a });
    };

    const setAlpha = (alpha: number) => {
        pick({ ...parsed, a: Math.min(Math.max(alpha, 0), 1) });
    };

    const setHue = (nextHue: number) => {
        setFromHueSatLight(nextHue, sat, light);
    };

    const handleSquarePointer = (event: React.PointerEvent<HTMLDivElement>) => {
        if (disabled) {
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const x = Math.min(Math.max(event.clientX - rect.left, 0), rect.width);
        const y = Math.min(Math.max(event.clientY - rect.top, 0), rect.height);

        setFromHueSatLight(hue, x / rect.width, 1 - y / rect.height);
    };

    const handleTextChange = (raw: string) => {
        setDraft(raw);
        const parsedValue = parseColor(raw);

        if (parsedValue) {
            onChange?.(toFormat(parsedValue, format));
        }
    };

    const saturationGradient = `linear-gradient(to right, hsl(${hue}, 0%, 50%), hsl(${hue}, 100%, 50%))`;

    return (
        <div>
            <FieldHeader node={node} formValues={formValues} labelId={node.name} />

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <button
                            type="button"
                            disabled={disabled}
                            aria-label="Pick a color"
                            className={cn(
                                'inline-flex h-9 w-full items-center gap-2 rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50',
                                error && 'border-destructive focus-visible:ring-destructive/30',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className="size-4 shrink-0 rounded-full border border-input"
                                style={{ backgroundColor: value ? (value as string) : 'transparent' }}
                            />
                            <span className={cn('flex-1 truncate text-left font-normal', !value && 'text-muted-foreground')}>
                                {(value as string) || node.placeholder || 'Pick a color'}
                            </span>
                        </button>
                    }
                />

                <PopoverContent className="w-64 p-3" align="start">
                    <div className="space-y-3">
                        <div
                            role="slider"
                            aria-label="Saturation and lightness"
                            aria-valuetext={`${Math.round(sat * 100)}% ${Math.round(light * 100)}%`}
                            onPointerDown={handleSquarePointer}
                            onPointerMove={(event) => {
                                if (event.buttons === 1) {
                                    handleSquarePointer(event);
                                }
                            }}
                            className="relative h-40 w-full cursor-crosshair touch-none rounded-md border border-input"
                            style={{ background: saturationGradient }}
                        >
                            <div
                                className="absolute inset-0"
                                style={{ background: 'linear-gradient(to top, #000, transparent)' }}
                            />
                            <div
                                className="absolute size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
                                style={{
                                    left: `${sat * 100}%`,
                                    top: `${(1 - light) * 100}%`,
                                    backgroundColor: `hsl(${hue}, ${sat * 100}%, ${light * 100}%)`,
                                }}
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <div
                                role="slider"
                                aria-label="Hue"
                                aria-valuenow={Math.round(hue)}
                                aria-valuemin={0}
                                aria-valuemax={360}
                                tabIndex={0}
                                onPointerDown={(event) => handleHuePointer(event, setHue)}
                                onPointerMove={(event) => {
                                    if (event.buttons === 1) {
                                        handleHuePointer(event, setHue);
                                    }
                                }}
                                className="relative h-3 w-full cursor-pointer touch-none rounded-full"
                                style={{
                                    background:
                                        'linear-gradient(to right, hsl(0,100%,50%), hsl(60,100%,50%), hsl(120,100%,50%), hsl(180,100%,50%), hsl(240,100%,50%), hsl(300,100%,50%), hsl(360,100%,50%))',
                                }}
                            >
                                <div
                                    className="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
                                    style={{ left: `${(hue / 360) * 100}%` }}
                                />
                            </div>
                        </div>

                        {hasAlpha ? (
                            <div className="flex items-center gap-2">
                                <div
                                    role="slider"
                                    aria-label="Opacity"
                                    aria-valuenow={Math.round(parsed.a * 100)}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    tabIndex={0}
                                    onPointerDown={(event) => handleAlphaPointer(event, setAlpha)}
                                    onPointerMove={(event) => {
                                        if (event.buttons === 1) {
                                            handleAlphaPointer(event, setAlpha);
                                        }
                                    }}
                                    className="relative h-3 w-full cursor-pointer touch-none rounded-full"
                                    style={{ background: `linear-gradient(to right, transparent, ${toFormat(parsed, 'hex')})` }}
                                >
                                    <div
                                        className="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
                                        style={{ left: `${parsed.a * 100}%` }}
                                    />
                                </div>
                                <span className="w-10 text-right text-xs tabular-nums text-muted-foreground">
                                    {Math.round(parsed.a * 100)}%
                                </span>
                            </div>
                        ) : null}

                        <div className="grid grid-cols-5 gap-1.5">
                            {PRESET_COLORS.map((preset) => {
                                const presetRgba = parseColor(preset);

                                return (
                                    <button
                                        key={preset}
                                        type="button"
                                        onClick={() => presetRgba && pick(presetRgba)}
                                        aria-label={`Set color ${preset}`}
                                        className={cn(
                                            'flex aspect-square items-center justify-center rounded-md border border-input',
                                            value === preset && 'ring-2 ring-ring',
                                        )}
                                        style={{ backgroundColor: preset }}
                                    >
                                        {value === preset ? <Check className="size-3 text-white mix-blend-difference" /> : null}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex items-center gap-2">
                            <Input
                                value={draft}
                                onChange={(event) => handleTextChange(event.target.value)}
                                aria-label="Color value"
                                className="h-8 text-sm"
                            />
                            <span className="w-12 text-right text-xs uppercase text-muted-foreground">{format}</span>
                        </div>
                    </div>
                </PopoverContent>
            </Popover>

            {error ? <p className="mt-0.5 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

function handleHuePointer(
    event: React.PointerEvent<HTMLDivElement>,
    apply: (hue: number) => void,
) {
    const rect = event.currentTarget.getBoundingClientRect();
    const x = Math.min(Math.max(event.clientX - rect.left, 0), rect.width);
    apply((x / rect.width) * 360);
}

function handleAlphaPointer(
    event: React.PointerEvent<HTMLDivElement>,
    apply: (alpha: number) => void,
) {
    const rect = event.currentTarget.getBoundingClientRect();
    const x = Math.min(Math.max(event.clientX - rect.left, 0), rect.width);
    apply(x / rect.width);
}