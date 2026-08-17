<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Refilament\Refilament\Panel\Panel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's logout response (the Refilament analogue of Filament's, whose
 * logout lands you back on the login page). Fortify's default response
 * redirects to the app root; the panel instead returns the visitor to its own
 * login page — or the dashboard when the panel has no login page — so the
 * user menu's Logout link keeps them inside the panel's auth flow.
 *
 * Registered in Refilament::registerAuthResponses() against the
 * LogoutResponse contract only when the consumer hasn't bound their own, so a
 * custom logout destination always wins.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function __construct(
        protected Panel $panel,
    ) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        // JSON clients (the typed endpoints) get a 204 like Fortify's default;
        // a browser follows the redirect to the panel's login page.
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect($this->panel->getLoginUrl() ?? $this->panel->getDashboardUrl());
    }
}
