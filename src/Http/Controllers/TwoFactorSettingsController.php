<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Refilament;

/**
 * Serve the panel's two-factor management page (the authenticated
 * "auth/…->twoFactorAuthentication()" management UI). Auth pages that map
 * onto a Fortify controller are rendered through Fortify's view contracts;
 * this page has no Fortify controller of its own, so it gets a tiny dedicated
 * action that resolves the configured page class (Panel::getTwoFactorSettingsPage())
 * and renders its Inertia component with the per-request view data — the same
 * shape as the shared PanelPageController, mounted behind the panel's auth
 * guard + the Inertia version header.
 */
class TwoFactorSettingsController
{
    public function show(Request $request): InertiaResponse
    {
        $refilament = app(Refilament::class);
        $page = $refilament->panel()->getTwoFactorSettingsPage();

        return Inertia::render(
            $page::getComponent(),
            $page::getViewData($request),
        )->rootView('refilament::app');
    }
}
