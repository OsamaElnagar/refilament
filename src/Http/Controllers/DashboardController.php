<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Refilament;

/**
 * Serve the panel dashboard (slice 1.9 "Dashboard") — the panel's registered
 * widgets, each serialized to a self-contained node per request. Registered
 * at /refilament (the panel's dashboard URL). Widget classes are built with
 * their own ::make(), so a widget's stat closures resolve server-side at
 * request time and never cross the wire; the React dashboard page renders the
 * snapshot nodes with no round trips.
 */
class DashboardController
{
    public function __invoke(Refilament $refilament): InertiaResponse
    {
        $widgets = array_map(
            static fn (string $class): array => $class::make()->toArray(),
            $refilament->panel()->getWidgets(),
        );

        return Inertia::render('refilament/dashboard', [
            'widgets' => array_values($widgets),
        ]);
    }
}
