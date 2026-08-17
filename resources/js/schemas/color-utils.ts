export interface RGBA {
    r: number;
    g: number;
    b: number;
    a: number;
}

export type ColorFormat = 'hex' | 'hsl' | 'rgb' | 'rgba';

const clamp = (value: number, min: number, max: number): number => Math.min(Math.max(value, min), max);

/** Parse any supported color format into RGBA (0-255 channels, 0-1 alpha). */
export function parseColor(value: string): RGBA | null {
    const normalized = value.trim().toLowerCase();

    if (normalized.startsWith('#')) {
        const hex = normalized.slice(1);

        if (/^[0-9a-f]{6}$/.test(hex)) {
            const bigint = parseInt(hex, 16);

            return {
                r: (bigint >> 16) & 255,
                g: (bigint >> 8) & 255,
                b: bigint & 255,
                a: 1,
            };
        }

        if (/^[0-9a-f]{3}$/.test(hex)) {
            const r = parseInt(hex[0] + hex[0], 16);
            const g = parseInt(hex[1] + hex[1], 16);
            const b = parseInt(hex[2] + hex[2], 16);

            return { r, g, b, a: 1 };
        }

        return null;
    }

    const rgbMatch = normalized.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/);

    if (rgbMatch) {
        return {
            r: clamp(Number(rgbMatch[1]), 0, 255),
            g: clamp(Number(rgbMatch[2]), 0, 255),
            b: clamp(Number(rgbMatch[3]), 0, 255),
            a: clamp(rgbMatch[4] === undefined ? 1 : Number(rgbMatch[4]), 0, 1),
        };
    }

    const hslMatch = normalized.match(/^hsl\(\s*([\d.]+)\s*,\s*([\d.]+)%\s*,\s*([\d.]+)%\s*\)$/);

    if (hslMatch) {
        return hslToRgb(Number(hslMatch[1]), Number(hslMatch[2]) / 100, Number(hslMatch[3]) / 100);
    }

    return null;
}

export function toHex({ r, g, b }: RGBA): string {
    const toTwo = (value: number): string => clamp(Math.round(value), 0, 255).toString(16).padStart(2, '0');

    return `#${toTwo(r)}${toTwo(g)}${toTwo(b)}`;
}

export function toRgb({ r, g, b }: RGBA): string {
    return `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
}

export function toRgba({ r, g, b, a }: RGBA): string {
    return `rgba(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)}, ${Number(a.toFixed(3))})`;
}

export function toHsl({ r, g, b }: RGBA): string {
    const [h, s, l] = rgbToHsl(r, g, b);

    return `hsl(${Math.round(h)}, ${Math.round(s * 100)}%, ${Math.round(l * 100)}%)`;
}

export function toFormat(color: RGBA, format: ColorFormat): string {
    switch (format) {
        case 'hsl':
            return toHsl(color);
        case 'rgb':
            return toRgb(color);
        case 'rgba':
            return toRgba(color);
        default:
            return toHex(color);
    }
}

export function rgbToHsl(r: number, g: number, b: number): [number, number, number] {
    const rn = r / 255;
    const gn = g / 255;
    const bn = b / 255;
    const max = Math.max(rn, gn, bn);
    const min = Math.min(rn, gn, bn);
    const delta = max - min;
    let h = 0;

    if (delta !== 0) {
        if (max === rn) {
            h = ((gn - bn) / delta) % 6;
        } else if (max === gn) {
            h = (bn - rn) / delta + 2;
        } else {
            h = (rn - gn) / delta + 4;
        }

        h *= 60;

        if (h < 0) {
            h += 360;
        }
    }

    const l = (max + min) / 2;
    const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1));

    return [h, s, l];
}

export function hslToRgb(h: number, s: number, l: number): RGBA {
    const hue = ((h % 360) + 360) % 360;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs(((hue / 60) % 2) - 1));
    const m = l - c / 2;
    let r = 0;
    let g = 0;
    let b = 0;

    if (hue < 60) {
        [r, g, b] = [c, x, 0];
    } else if (hue < 120) {
        [r, g, b] = [x, c, 0];
    } else if (hue < 180) {
        [r, g, b] = [0, c, x];
    } else if (hue < 240) {
        [r, g, b] = [0, x, c];
    } else if (hue < 300) {
        [r, g, b] = [x, 0, c];
    } else {
        [r, g, b] = [c, 0, x];
    }

    return {
        r: Math.round((r + m) * 255),
        g: Math.round((g + m) * 255),
        b: Math.round((b + m) * 255),
        a: 1,
    };
}

/** A set of preset swatches shown in the picker. */
export const PRESET_COLORS = [
    '#ef4444',
    '#f97316',
    '#f59e0b',
    '#84cc16',
    '#22c55e',
    '#10b981',
    '#06b6d4',
    '#3b82f6',
    '#6366f1',
    '#8b5cf6',
    '#a855f7',
    '#ec4899',
    '#64748b',
    '#000000',
    '#ffffff',
];