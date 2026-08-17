<?php

declare(strict_types=1);

namespace Refilament\Refilament\Auth\Pages;

use Illuminate\Http\Request;

/**
 * Base for the panel's first-party auth pages (docs/ROADMAP.md "1.9 auth
 * pages") — the Refilament analogue of Filament's `SimplePage` auth pages
 * (Login, Register, EmailVerificationPrompt, RequestPasswordReset,
 * ResetPassword), translated for an Inertia + React shell.
 *
 * Each auth page is *pure config*: it names the Inertia component the panel
 * renders for a route and any per-request props that component needs. The
 * panel's `->login()`, `->registration()`, `->passwordReset()` and
 * `->emailVerification()` methods point at these classes, and a consumer
 * overrides any page by passing their own class — the Filament story. The
 * actual authentication machinery (login pipeline, rate limiting, the
 * password broker, email verification, two-factor challenge) is Fortify's,
 * reached through Fortify's controllers; the page class only decides what
 * the visitor sees.
 */
abstract class AuthPage
{
    /**
     * The Inertia component served for this page ('auth/login' → the
     * package bundle's resources/js/pages/auth/login.tsx). A consumer's
     * custom page class names their own component.
     */
    abstract public static function getComponent(): string;

    /**
     * The URL path this page serves under, relative to the panel's prefix
     * ('/login' → '/{panel path}/login'). The `{token}` placeholder in the
     * reset-password path is the route's parameter name.
     */
    abstract public static function getPath(): string;

    /**
     * Extra Inertia props for this page, computed per request — e.g. the
     * reset token + email on the reset-password page, or the resend status
     * on the verify-email prompt.
     *
     * @return array<string, mixed>
     */
    public static function getViewData(Request $request): array
    {
        return [];
    }
}
