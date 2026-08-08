/**
 * Read the CSRF token from the document meta tag for fetch-based requests.
 * Falls back to null when the tag is absent (the server then rejects the
 * request — fail visible, not silent).
 */
export function readCsrfToken(): string | null {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}
