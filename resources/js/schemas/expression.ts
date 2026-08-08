/**
 * Computed-field expressions (slice C3) — the honest counterpart to
 * Filament's reactive `afterStateUpdated(Get, Set)` arithmetic. Where
 * Filament re-runs PHP closures on every keystroke, we serialize a tiny,
 * deliberately safe expression DSL and evaluate it client-side against live
 * form values: no `eval()`, no server round trip.
 *
 * Grammar: numbers, field references (identifiers), `+ - * / %`, unary
 * `+/-` and parentheses, with normal operator precedence. Any unresolvable
 * input (blank field, non-numeric value, division by zero, malformed
 * expression) yields `null` so a half-filled form shows a blank computed
 * field instead of `NaN` or a crash.
 */

type Token =
    | { kind: 'number'; value: number }
    | { kind: 'ident'; name: string }
    | { kind: 'op'; value: '+' | '-' | '*' | '/' | '%' }
    | { kind: 'lparen' }
    | { kind: 'rparen' };

const isDigit = (char: string): boolean => char >= '0' && char <= '9';
const isIdentStart = (char: string): boolean => /[a-zA-Z_]/.test(char);
const isIdentPart = (char: string): boolean => /[a-zA-Z0-9_]/.test(char);

function tokenize(source: string): Token[] {
    const tokens: Token[] = [];
    let index = 0;

    while (index < source.length) {
        const char = source[index];

        if (/\s/.test(char)) {
            index++;

            continue;
        }

        if (isDigit(char) || char === '.') {
            let end = index;

            while (end < source.length && (isDigit(source[end]) || source[end] === '.')) {
                end++;
            }

            const value = Number(source.slice(index, end));

            if (!Number.isFinite(value)) {
                return [];
            }

            tokens.push({ kind: 'number', value });
            index = end;

            continue;
        }

        if (isIdentStart(char)) {
            let end = index;

            while (end < source.length && isIdentPart(source[end])) {
                end++;
            }

            tokens.push({ kind: 'ident', name: source.slice(index, end) });
            index = end;

            continue;
        }

        if (char === '(') {
            tokens.push({ kind: 'lparen' });
            index++;

            continue;
        }

        if (char === ')') {
            tokens.push({ kind: 'rparen' });
            index++;

            continue;
        }

        if (char === '+' || char === '-' || char === '*' || char === '/' || char === '%') {
            tokens.push({ kind: 'op', value: char });
            index++;

            continue;
        }

        return [];
    }

    return tokens;
}

/** Coerce a live form value to a finite number, or null when it can't be used. */
function toNumber(value: unknown): number | null {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (trimmed === '') {
            return null;
        }

        const parsed = Number(trimmed);

        return Number.isFinite(parsed) ? parsed : null;
    }

    if (typeof value === 'boolean') {
        return value ? 1 : 0;
    }

    return null;
}

export function evaluateExpression(expression: string, values: Record<string, unknown>): number | null {
    const tokens = tokenize(expression);

    if (tokens.length === 0) {
        return null;
    }

    let position = 0;

    const peek = (): Token | undefined => tokens[position];
    const next = (): Token | undefined => tokens[position++];

    function parseAdditive(): number | null {
        let left = parseMultiplicative();

        while (true) {
            const token = peek();

            if (!token || token.kind !== 'op' || (token.value !== '+' && token.value !== '-')) {
                break;
            }

            next();
            const right = parseMultiplicative();

            left = left === null || right === null ? null : token.value === '+' ? left + right : left - right;
        }

        return left;
    }

    function parseMultiplicative(): number | null {
        let left = parseUnary();

        while (true) {
            const token = peek();

            if (!token || token.kind !== 'op' || (token.value !== '*' && token.value !== '/' && token.value !== '%')) {
                break;
            }

            next();
            const right = parseUnary();

            if (left === null || right === null || (token.value !== '*' && right === 0)) {
                left = null;

                continue;
            }

            left = token.value === '*' ? left * right : token.value === '/' ? left / right : left % right;
        }

        return left;
    }

    function parseUnary(): number | null {
        const token = peek();

        if (token?.kind === 'op' && (token.value === '+' || token.value === '-')) {
            next();
            const operand = parseUnary();

            return operand === null ? null : token.value === '-' ? -operand : operand;
        }

        return parsePrimary();
    }

    function parsePrimary(): number | null {
        const token = next();

        if (!token) {
            return null;
        }

        if (token.kind === 'number') {
            return token.value;
        }

        if (token.kind === 'ident') {
            return toNumber(values[token.name]);
        }

        if (token.kind === 'lparen') {
            const inner = parseAdditive();

            return next()?.kind === 'rparen' ? inner : null;
        }

        return null;
    }

    const result = parseAdditive();

    // Trailing junk after a complete expression is a malformed formula.
    return position === tokens.length ? result : null;
}
