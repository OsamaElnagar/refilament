<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Pages\Page;

class ResourcePageController
{
    /**
     * Render one page of a discovered resource (slice 1.6 — docs/ROADMAP.md
     * "1.6 Page system"). The package registers a route per entry in every
     * resource's getPages() map at boot — GET /refilament/{resource}{path}
     * for each page slot, all where()-gated to the ids discovered then and
     * named refilament.resource.{page} (one shared route per page name, not
     * per resource) — and this single action serves them all. The page
     * class is resolved from the route name's trailing segment, and the
     * page builds its own Inertia payload; record segments are constrained
     * to [0-9]+ so page slugs like /create or /stats never collide with
     * record ids.
     */
    public function show(Request $request, string $resource, ?string $record = null): InertiaResponse
    {
        // Resolved inside the method rather than injected: the dispatcher
        // splices route params + container deps together positionally, and
        // the optional $record in between breaks that ordering.
        $refilament = app(Refilament::class);

        $class = $refilament->getResourceClass($resource);

        // Unreachable in normal runs — the route's where() constraint only
        // admits ids discovered at boot. It guards stale route caches: a
        // resource removed after route:cache still matches the baked regex
        // but no longer resolves, so without this the render would crash.
        if ($class === null) {
            abort(404);
        }

        $pageName = Str::afterLast((string) $request->route()->getName(), '.');

        $registration = $class::getPages()[$pageName] ?? null;

        if ($registration === null) {
            abort(404);
        }

        /** @var class-string<Page> $page */
        $page = $registration->getPage();

        return Inertia::render(
            $page::getInertiaComponent(),
            $page::getPayload($resource, $refilament, $record),
        );
    }
}
