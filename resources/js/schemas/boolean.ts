/**
 * Coerce a field value to a boolean for the checkbox/toggle controls.
 *
 * Real booleans pass through; numeric and string forms (`1`/`0`, `'1'`/`'0'`,
 * `'true'`/`'false'`) are normalized too, so a future state cast or a server
 * that serializes string booleans can never render an inverted control
 * (`Boolean('0')` is `true` — the naive coercion is a footgun).
 */
export function normalizeBoolean(value: unknown, fallback: boolean): boolean {
    if (value === undefined || value === null) {
        return fallback;
    }

    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        const normalized = value.toLowerCase();

        return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
    }

    return false;
}
