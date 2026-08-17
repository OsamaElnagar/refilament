<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Actions;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as BaseRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\LoginRateLimiter;
use Refilament\Refilament\Refilament;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's two-factor challenge bounce (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md).
 *
 * Fortify's stock `RedirectIfTwoFactorAuthenticatable` hardcodes
 * `route('two-factor.login')` in its challenge response. Under the panel's
 * scoped route names that name no longer exists, so the challenge would throw
 * on every 2FA login. This subclass keeps Fortify's whole login pipeline
 * (credential validation, challenge session data, event dispatch) and only
 * redirects to the panel's own `two-factor.login` route instead.
 *
 * Rebound over the `RedirectsIfTwoFactorAuthenticatable` contract in
 * `Refilament::registerAuthResponses()` when the panel enables two-factor
 * authentication — and only when the consumer hasn't rebound it themselves.
 */
class RedirectIfTwoFactorAuthenticatable extends BaseRedirectIfTwoFactorAuthenticatable
{
    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        protected Refilament $refilament,
    ) {
        parent::__construct($guard, $limiter);
    }

    /**
     * Redirect the visitor to the panel's own two-factor challenge page.
     *
     * The signature must stay untyped to match Fortify's parent method
     * (`RedirectIfTwoFactorAuthenticatable::twoFactorChallengeResponse`),
     * which declares no parameter or return types — PHP would reject a child
     * that narrows them.
     *
     * @param  Request  $request
     * @param  mixed  $user
     * @return Response
     */
    protected function twoFactorChallengeResponse($request, $user) // @pest-ignore-type
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route('refilament.'.$this->refilament->panel()->getId().'.auth.two-factor.login');
    }
}
