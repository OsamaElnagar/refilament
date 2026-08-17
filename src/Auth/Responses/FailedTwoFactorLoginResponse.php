<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;
use Refilament\Refilament\Panel\Panel;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's failed two-factor login response
 * (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md).
 *
 * Fortify's stock response hardcodes `route('two-factor.login')` when a
 * submitted two-factor code is invalid. Under the panel's scoped route names
 * that name no longer exists, so a failed challenge would throw. This keeps
 * Fortify's error semantics (per-field message, JSON validation errors) and
 * only redirects back to the panel's own challenge page.
 *
 * Registered against the `FailedTwoFactorLoginResponse` contract in
 * `Refilament::registerAuthResponses()` when the panel enables two-factor
 * authentication — a consumer's own binding always wins.
 */
class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    public function __construct(
        protected Panel $panel,
    ) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        [$key, $message] = $request->filled('recovery_code')
            ? ['recovery_code', __('The provided two factor recovery code was invalid.')]
            : ['code', __('The provided two factor authentication code was invalid.')];

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                $key => [$message],
            ]);
        }

        return redirect()
            ->route('refilament.'.$this->panel->getId().'.auth.two-factor.login')
            ->withErrors([$key => $message]);
    }
}
