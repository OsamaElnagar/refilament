/**
 * Minimal date helpers for the date-time picker.
 *
 * The picker receives its value as a plain string in the PHP `format` tokens
 * (`Y-m-d H:i:s`, `Y-m-d`, `H:i`, ...) and renders it with day.js-style
 * `displayFormat` tokens (`M j, Y H:i`). No date library is bundled, so both
 * sides are handled here with a tiny tokenizer — bounded to the token set the
 * field actually emits.
 */

const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

/** PHP state tokens (zero-padded numbers). */
const STATE_TOKENS: Record<string, (date: Date) => string> = {
    Y: (date) => String(date.getFullYear()),
    m: (date) => pad(date.getMonth() + 1),
    d: (date) => pad(date.getDate()),
    H: (date) => pad(date.getHours()),
    i: (date) => pad(date.getMinutes()),
    s: (date) => pad(date.getSeconds()),
};

/** day.js display tokens (month name, unpadded day, ...). */
const DISPLAY_TOKENS: Record<string, (date: Date) => string> = {
    Y: (date) => String(date.getFullYear()),
    M: (date) => MONTH_NAMES[date.getMonth()],
    j: (date) => String(date.getDate()),
    H: (date) => pad(date.getHours()),
    i: (date) => pad(date.getMinutes()),
    s: (date) => pad(date.getSeconds()),
};

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** The order state tokens appear in a PHP format string (used for parsing). */
function tokenOrder(format: string): Array<keyof typeof STATE_TOKENS> {
    const order: Array<keyof typeof STATE_TOKENS> = [];

    for (const char of format) {
        if (char in STATE_TOKENS && !order.includes(char as keyof typeof STATE_TOKENS)) {
            order.push(char as keyof typeof STATE_TOKENS);
        }
    }

    return order;
}

/**
 * Parse a state string (e.g. `2025-01-15 10:30:00`) into a Date using the
 * PHP `format` tokens. Returns `null` when it can't be parsed. A missing date
 * portion (time-only picker) attaches today's date so calendar math still has
 * a concrete Date; serialization drops it back out.
 */
export function parseState(value: string | undefined, format: string): Date | null {
    if (!value) {
        return null;
    }

    const order = tokenOrder(format);

    if (order.length === 0) {
        return null;
    }

    let pattern = '^';
    let captureIndex = 0;
    const captureToken: Record<number, keyof typeof STATE_TOKENS> = {};

    for (const char of format) {
        if (char in STATE_TOKENS) {
            pattern += `(\\d{1,4})`;
            captureToken[captureIndex++] = char as keyof typeof STATE_TOKENS;
        } else {
            pattern += escapeRegex(char);
        }
    }

    pattern += '$';

    const match = new RegExp(pattern).exec(value.trim());

    if (!match) {
        return null;
    }

    const parts: Record<string, number> = {};

    for (let index = 0; index < captureIndex; index++) {
        const token = captureToken[index];
        parts[token] = Number(match[index + 1]);
    }

    const now = new Date();

    const year = parts.Y ?? now.getFullYear();
    const month = (parts.m ?? now.getMonth() + 1) - 1;
    const day = parts.d ?? now.getDate();
    const hours = parts.H ?? 0;
    const minutes = parts.i ?? 0;
    const seconds = parts.s ?? 0;

    const date = new Date(year, month, day, hours, minutes, seconds, 0);

    return Number.isNaN(date.getTime()) ? null : date;
}

/** Serialize a Date back into the PHP `format` state string. */
export function serializeState(date: Date | null, format: string): string {
    if (!date) {
        return '';
    }

    let result = '';

    for (const char of format) {
        const formatter = STATE_TOKENS[char];

        result += formatter ? formatter(date) : char;
    }

    return result;
}

/** Render a Date with day.js-style display tokens. */
export function formatDisplay(date: Date | null, format: string): string {
    if (!date) {
        return '';
    }

    let result = '';

    for (const char of format) {
        const formatter = DISPLAY_TOKENS[char];

        result += formatter ? formatter(date) : char;
    }

    return result;
}

export function isSameDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth() &&
        left.getDate() === right.getDate()
    );
}

export function isToday(date: Date): boolean {
    return isSameDay(date, new Date());
}

/** Number of leading blank cells before the 1st of the focused month's grid. */
export function leadingBlankCount(focused: Date, firstDayOfWeek: number): number {
    const firstOfMonth = new Date(focused.getFullYear(), focused.getMonth(), 1);

    return (firstOfMonth.getDay() - (firstDayOfWeek % 7) + 7) % 7;
}

/** Total days in the focused month. */
export function daysInMonth(focused: Date): number {
    return new Date(focused.getFullYear(), focused.getMonth() + 1, 0).getDate();
}

export function addMonths(focused: Date, amount: number): Date {
    return new Date(focused.getFullYear(), focused.getMonth() + amount, 1);
}

export function monthLabel(focused: Date): string {
    return `${MONTH_NAMES[focused.getMonth()]} ${focused.getFullYear()}`;
}

export function dayDate(focused: Date, day: number): Date {
    return new Date(focused.getFullYear(), focused.getMonth(), day);
}

function escapeRegex(char: string): string {
    return /[.*+?^${}()|[\]\\]/.test(char) ? `\\${char}` : char;
}