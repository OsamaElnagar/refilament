<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's password-reset success response
 * (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md).
 *
 * Fortify's stock response evaluates `route('login')` eagerly as the default
 * redirect target — under the panel's scoped route names that name belongs to
 * the app (or does not exist at all), so every successful reset would throw or
 * land on the wrong page. This keeps the same `fortify.home` / consumer
 * `fortify.redirects.password-reset` target Fortify resolves — minus the
 * global-name dependency. The visitor is not auto-authenticated, matching
 * Fortify's behavior.
 *
 * Registered against the `PasswordResetResponse` contract in
 * `Refilament::registerAuthResponses()` when the panel enables password reset
 * — a consumer's own binding always wins.
 */
class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(
        protected string $status,
    ) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect(Fortify::redirects('password-reset', config('fortify.home')))
                ->with('status', trans($this->status));
    }
}
