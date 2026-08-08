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

        $pageClass = $refilament->resolvePanelPage((string) $request->route('page'));

        if ($pageClass === null) {
            abort(404);
        }

        return Inertia::render(
            $pageClass::getInertiaComponent(),
            $pageClass::getPanelViewData($refilament),
        )->rootView('refilament::app');
    }
}
