<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Refilament\Refilament\Refilament;
use Refilament\Refilament\Resources\Resource;

/**
 * Serve a cluster's own URL (the page-clusters slice) — a cluster is a
 * navigation container and never renders: hitting /refilament/{cluster}
 * redirects to the first accessible clustered component, mirroring
 * Filament's Cluster::mount() (which redirects to the first sub-navigation
 * item). A cluster whose components the current user cannot reach 404s.
 */
class ClusterRedirectController
{
    public function __invoke(Request $request, string $cluster): RedirectResponse
    {
        $refilament = app(Refilament::class);

        $clusterClass = $refilament->getClusterClass($cluster);

        if ($clusterClass === null) {
            abort(404);
        }

        foreach ($clusterClass::getClusteredComponents() as $component) {
            if (is_subclass_of($component, Resource::class)) {
                if (! $component::canAccess()) {
                    continue;
                }

                return redirect($component::getNavigationUrl());
            }

            if (method_exists($component, 'canAccess') && ! $component::canAccess()) {
                continue;
            }

            return redirect($refilament->panel()->url('/'.$component::getSlugPath()));
        }

        abort(404);
    }
}
