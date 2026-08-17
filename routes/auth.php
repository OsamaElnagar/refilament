<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;
use Refilament\Refilament\Http\Controllers\ProfileController;
use Refilament\Refilament\Http\Controllers\TwoFactorChallengeController;
use Refilament\Refilament\Http\Controllers\TwoFactorSettingsController;
use Refilament\Refilament\Http\Middleware\AppendInertiaVersion;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;

// The panel's first-party auth routes (docs/ROADMAP.md "1.9 auth pages"),
// required from Refilament::registerAuthRoutes() inside the panel's `web`
// middleware group and URL prefix — every URI below is therefore relative to
// the panel path (a consumer's ->path('admin') moves the whole auth surface
// with the rest of the panel). The routes delegate to Fortify's own
// controllers, so the login pipeline (rate limiting, canonicalization, the
// two-factor challenge), the password broker, email verification and the
// account-management endpoints are all Fortify's machinery — this table only
// decides where they live and which panel pages render. The panel's access
// gate is deliberately NOT mounted here (login can't require auth); the
// version header IS, so the auth pages carry the Inertia handshake.
//
// Route names are panel-scoped — `refilament.{panelId}.auth.*` — never
// Fortify's global names (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md). A
// consumer app that also enables Fortify keeps its own `login`,
// `password.confirm`, `two-factor.*`, ... names; the panel's copies live in
// their own namespace, so nothing collides and `route('login')` in the app
// can never silently resolve to `/admin/login`. Where Fortify's or Laravel's
// internals hardcode a global name, the panel passes its scoped name or
// rebinds the contract explicitly (see the individual spots below).

$panel = app(Refilament::class)->panel();
$guard = $panel->getAuthGuard();
$panelId = $panel->getId();

// The authenticated surface (profile, password confirmation, 2FA management)
// is gated by the panel's own Authenticate middleware, always enforced — the
// unauthenticated visitor is redirected to the panel's login page (or 401 for
// JSON), not bounced through the framework `auth` middleware's global `login`
// route name. The guest middleware on the unauthenticated pages keeps the
// framework default (authenticated visitors go to the app's dashboard/home).
$auth = PanelAuthenticate::class.':'.$guard;
$guest = 'guest:'.$guard;

$loginLimiter = config('fortify.limiters.login');
$twoFactorLimiter = config('fortify.limiters.two-factor');
$verificationLimiter = config('fortify.limiters.verification', '6,1');

Route::name("refilament.{$panelId}.auth.")->group(function () use ($panel, $auth, $guest, $loginLimiter, $twoFactorLimiter, $verificationLimiter): void {
    // Authentication...
    if ($panel->hasLogin()) {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])
            ->middleware([$guest, AppendInertiaVersion::class])
            ->name('login');
    }

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(array_filter([$guest, $loginLimiter ? 'throttle:'.$loginLimiter : null]))
        ->name('login.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware([$auth])
        ->name('logout');

    // Password reset...
    if ($panel->hasPasswordReset()) {
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
            ->middleware([$guest, AppendInertiaVersion::class])
            ->name('password.request');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->middleware([$guest, AppendInertiaVersion::class])
            ->name('password.reset');

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware([$guest])
            ->name('password.email');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->middleware([$guest])
            ->name('password.update');
    }

    // Registration...
    if ($panel->hasRegistration()) {
        Route::get('register', [RegisteredUserController::class, 'create'])
            ->middleware([$guest, AppendInertiaVersion::class])
            ->name('register');

        Route::post('register', [RegisteredUserController::class, 'store'])
            ->middleware([$guest])
            ->name('register.store');
    }

    // Email verification...
    if ($panel->hasEmailVerification()) {
        Route::get('email/verify', [EmailVerificationPromptController::class, '__invoke'])
            ->middleware([$auth, AppendInertiaVersion::class])
            ->name('email-verification.prompt');

        Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
            ->middleware([$auth, 'signed', 'throttle:'.$verificationLimiter])
            ->name('email-verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware([$auth, 'throttle:'.$verificationLimiter])
            ->name('email-verification.send');
    }

    // Profile information + password updates (the consumer's settings pages use
    // these endpoints — the panel registers the routes, the UI stays theirs)...
    Route::put('user/profile-information', [ProfileInformationController::class, 'update'])
        ->middleware([$auth])
        ->name('profile-information.update');

    Route::put('user/password', [PasswordController::class, 'update'])
        ->middleware([$auth])
        ->name('user-password.update');

    // Profile page (Filament's ->profile() — EditProfile with name/email/password
    // + 2FA management). Behind the panel auth guard + the Inertia version header.
    if ($panel->hasProfile()) {
        Route::get('user/profile', [ProfileController::class, 'show'])
            ->middleware([$auth, AppendInertiaVersion::class])
            ->name('profile');
    }

    // Password confirmation...
    Route::get('user/confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->middleware([$auth, AppendInertiaVersion::class])
        ->name('password.confirm');

    Route::get('user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])
        ->middleware([$auth])
        ->name('password.confirmation');

    Route::post('user/confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware([$auth])
        ->name('password.confirm.store');

    // Two-factor authentication...
    if ($panel->hasTwoFactorAuthentication()) {
        // The challenge page is served by a wrapper controller: Fortify's stock
        // one hardcodes `route('login')` when the URL is hit without a
        // challenged user, which would resolve the app's login (or nothing)
        // under scoped names. The wrapper bounces to the panel's own login.
        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
            ->middleware([$guest, AppendInertiaVersion::class])
            ->name('two-factor.login');

        Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([$guest, $twoFactorLimiter ? 'throttle:'.$twoFactorLimiter : null]))
            ->name('two-factor.login.store');

        // Laravel's `password.confirm` middleware defaults to redirecting to a
        // route literally named `password.confirm` — pass the panel's scoped
        // name explicitly so the 2FA-management endpoints bounce to the
        // panel's own confirmation page instead.
        $twoFactorMiddleware = Features::optionEnabled(
            Features::twoFactorAuthentication(),
            'confirmPassword',
        ) ? [$auth, 'password.confirm:refilament.'.$panel->getId().'.auth.password.confirm'] : [$auth];

        Route::post('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.enable');

        Route::post('user/confirmed-two-factor-authentication', [ConfirmedTwoFactorAuthenticationController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.confirm');

        Route::delete('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.disable');

        Route::get('user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.qr-code');

        Route::get('user/two-factor-secret-key', [TwoFactorSecretKeyController::class, 'show'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.secret-key');

        Route::get('user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.recovery-codes');

        Route::post('user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.regenerate-recovery-codes');

        // The panel's own two-factor management page (enable/disable, QR code,
        // recovery codes) — an authenticated shell page, so it sits behind the
        // panel's auth guard + the Inertia version header (the management
        // endpoints below it carry their own password-confirmation middleware).
        Route::get('user/two-factor', [TwoFactorSettingsController::class, 'show'])
            ->middleware([$auth, AppendInertiaVersion::class])
            ->name('two-factor.settings');
    }
});
