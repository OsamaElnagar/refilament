<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Refilament\Refilament\Refilament;

/**
 * Serve the panel's profile page (Filament's `->profile()` — the
 * EditProfile surface at /{{path}}/user/profile). Like
 * TwoFactorSettingsController, this is a tiny dedicated action that resolves
 * the configured page class (Panel::getProfilePage()) and renders its Inertia
 * component with the per-request view data, mounted behind the panel's auth
 * guard + the Inertia version header.
 */
class ProfileController
{
    public function show(Request $request): InertiaResponse
    {
        $refilament = app(Refilament::class);
        $page = $refilament->panel()->getProfilePage();

        return Inertia::render(
            $page::getComponent(),
            $page::getViewData($request),
        )->rootView('refilament::app');
    }
}
