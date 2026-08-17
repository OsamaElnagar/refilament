<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Refilament;

/**
 * Serve one standalone panel page (slice 1.9 "->pages([...])").
 *
 * The panel auto-registers one shared route — GET /refilament/{page},
 * named refilament.page — gated to the slugs of every standalone page, and
 * this single action serves them all. The page class is resolved from the
 * {page} route segment (the inverse of the route's where() gate), and the
 * page builds its own Inertia payload from getPanelViewData() before being
 * rendered with its getInertiaComponent().
 */
class PanelPageController
{
    public function show(Request $request): InertiaResponse
    {
        $refilament = app(Refilament::class);

        // Clustered pages serve at /{cluster}/{page} (the page-clusters
        // slice) — the full slug the panel resolves is cluster + page
        // basename, matching the page's prefixed getSlug().
        $slug = (string) $request->route('page');

        if ($request->route('cluster') !== null) {
            $slug = $request->route('cluster').'/'.$slug;
        }

        $pageClass = $refilament->resolvePanelPage($slug);

        if ($pageClass === null) {
            abort(404);
        }

        // The page's form (page-forms slice), infolist (page-infolists
        // slice) and table (pages-as-tables slice) payloads merge under the
        // page's own view data — the page's getPanelViewData() is spread
        // last so bespoke page props always win over the generic form/
        // infolist/table keys. A page hosting none contributes nothing.
        $payload = [
            ...$pageClass::serializePageForm(),
            ...$pageClass::serializePageInfolist(),
            ...$pageClass::serializePageTable(),
            ...$pageClass::getPanelViewData($refilament),
        ];

        // Standalone page breadcrumbs (slice 1.11) — serialized when the page
        // declares any and the panel's global toggle is on (the resource-page
        // serializer applies the same gate). A clustered page's chain gains
        // its cluster crumb (the page-clusters slice), linked to the cluster
        // URL.
        if ($refilament->panel()->hasBreadcrumbs()) {
            $breadcrumbs = $pageClass::getBreadcrumbs();

            // A clustered page with no declared crumbs still shows the
            // cluster chain: [cluster crumb → page crumb] (the page-clusters
            // slice).
            if ($breadcrumbs === [] && $pageClass::isClustered()) {
                $breadcrumbs = [['label' => $pageClass::getNavigationLabel()]];
            }

            $breadcrumbs = $pageClass::unshiftClusterBreadcrumbs($breadcrumbs);

            if ($breadcrumbs !== []) {
                $payload['breadcrumbs'] = $breadcrumbs;
            }
        }

        return Inertia::render(
            $pageClass::getInertiaComponent(),
            $payload,
        )->rootView('refilament::app');
    }
}
