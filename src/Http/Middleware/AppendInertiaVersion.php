<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

/**
 * Append the Inertia asset version to every panel response — the panel's own
 * "AppendVersion" middleware, mirroring the version handshake Inertia's
 * `Middleware` provides for consumers who append it to `web`.
 *
 * Inertia's protocol hands the compiled-assets version to the client (the
 * `X-Inertia-Version` header, plus `page.version`) and the client echoes it
 * back on subsequent requests so the server can 409 a stale visit. The
 * header is normally added by the consumer's `HandleInertiaRequests` — which
 * only runs when the consumer appends it to the `web` group. This middleware
 * guarantees the panel's own responses carry the version regardless of the
 * consumer's setup: a bare Laravel app that installs the panel and never
 * touches Inertia still gets a correct version handshake.
 *
 * The version resolves the same way Inertia resolves it:
 *
 * - a version the consumer registered globally (`Inertia::version(...)`, or
 *   the per-request closure any Inertia middleware set while handling this
 *   request — which runs before the panel chain) wins;
 * - otherwise the compiled-assets manifest (`build/manifest.json`, falling
 *   back to `mix-manifest.json`) is hashed with xxh128, exactly like
 *   `Inertia\Middleware::version()`;
 * - no version source → no header, since there would be nothing to compare
 *   against.
 *
 * A response that already carries the header is left untouched, so a
 * consumer that does append its own Inertia middleware stays authoritative.
 */
class AppendInertiaVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof Response && ! $response->headers->has(Header::VERSION)) {
            if ($version = $this->resolveVersion()) {
                $response->headers->set(Header::VERSION, $version);
            }
        }

        return $response;
    }

    protected function resolveVersion(): ?string
    {
        $version = Inertia::getVersion();

        if ($version !== '') {
            return $version;
        }

        if (config('app.asset_url')) {
            // xxh128 is always available, so hash() cannot return false here.
            return (string) hash('xxh128', (string) config('app.asset_url'));
        }

        foreach (['build/manifest.json', 'mix-manifest.json'] as $manifest) {
            $path = public_path($manifest);

            if (file_exists($path)) {
                return (string) hash_file('xxh128', $path);
            }
        }

        return null;
    }
}
