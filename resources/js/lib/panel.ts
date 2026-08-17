/**
 * Panel URL helpers — the frontend counterpart to `Panel::path()`.
 *
 * A consumer's `->path('admin')` moves every panel route server-side, and the
 * shell must build its own URLs (search, notifications, table and relation
 * endpoints, page links) under the same prefix. The path is part of the
 * server-shared `refilament.panel` prop; the initial page is always present
 * in the root view's `#app[data-page]`, so we read it once on first use and
 * cache it — no per-component `usePage()` plumbing needed for plain fetch
 * helpers that run outside React components.
 */

let cachedPath: string | null = null;

interface PanelPageShape {
    props?: {
        refilament?: {
            panel?: {
                path?: string;
            };
        };
    };
}

function currentPath(): string {
    if (cachedPath !== null) {
        return cachedPath;
    }

    cachedPath = 'refilament';

    const raw = readPagePayload();

    if (raw) {
        try {
            const path = (JSON.parse(raw) as PanelPageShape).props?.refilament?.panel?.path;

            if (path) {
                cachedPath = path;
            }
        } catch {
            // Not the Inertia page payload — keep the default.
        }
    }

    return cachedPath;
}

/**
 * The serialized Inertia page payload from the root view. Newer Inertia
 * renders it in a dedicated script tag (`<script data-page="app"
 * type="application/json">`) — the same node `@inertiajs/core` reads — with
 * the `<div id="app">` carrying no attribute at all; older versions embed it
 * as `data-page` on the app div. Read both so a consumer's
 * `->path('admin')` resolves no matter which format their Inertia renders.
 */
function readPagePayload(): string | null {
    const script = document.querySelector<HTMLScriptElement>('script[data-page="app"][type="application/json"]');

    if (script?.textContent) {
        return script.textContent;
    }

    return document.getElementById('app')?.getAttribute('data-page') ?? null;
}

/**
 * Build an absolute URL under the panel's prefix — `panelUrl('/table/user')`
 * → `/refilament/table/user` (or `/admin/table/user` after a consumer sets
 * `->path('admin')`). The prefix defaults to `refilament` when the page data
 * hasn't seeded a path yet.
 */
export function panelUrl(suffix: string): string {
    const base = currentPath().replace(/^\/+|\/+$/g, '');
    const clean = suffix.replace(/^\/+/, '');

    return `/${base}${clean ? `/${clean}` : ''}`;
}

/** The panel's URL prefix as a bare segment (no slashes). */
export function getPanelPath(): string {
    return currentPath();
}
