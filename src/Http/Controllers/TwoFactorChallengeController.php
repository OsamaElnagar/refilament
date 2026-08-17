<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Refilament\Refilament\Refilament;

/**
 * The panel's two-factor challenge page controller
 * (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md).
 *
 * Fortify's stock `TwoFactorAuthenticatedSessionController::create()` hardcodes
 * `route('login')` when the challenge URL is hit without a challenged user in
 * the session. Under the panel's scoped route names that name belongs to the
 * app (or does not exist at all), so a direct hit on the challenge URL would
 * bounce somewhere wrong or throw. This wrapper keeps Fortify's challenge
 * rendering and only sends the stray visitor to the panel's own login page.
 */
class TwoFactorChallengeController extends TwoFactorAuthenticatedSessionController
{
    public function __construct(
        StatefulGuard $guard,
        protected Refilament $refilament,
    ) {
        parent::__construct($guard);
    }

    /**
     * Show the two factor authentication challenge view.
     */
    public function create(TwoFactorLoginRequest $request): TwoFactorChallengeViewResponse
    {
        if (! $request->hasChallengedUser()) {
            throw new HttpResponseException(
                redirect()->route('refilament.'.$this->refilament->panel()->getId().'.auth.login'),
            );
        }

        return parent::create($request);
    }
}
