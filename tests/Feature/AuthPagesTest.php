<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\RecoveryCode;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Refilament\Refilament\Auth\Pages\AuthPage;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;
use Workbench\App\Models\User;

// Arm the panel's first-party auth pages + the auth gate before each test,
// then (re)register the auth routes — the service provider's booted() hook
// already ran during application bootstrap (when the panel was still the
// permissive default), so arming afterwards needs an explicit re-registration.
beforeEach(function () {
    app(Refilament::class)->panel()
        ->authMiddleware([PanelAuthenticate::class])
        ->login()
        ->registration()
        ->passwordReset()
        ->emailVerification()
        ->twoFactorAuthentication()
        ->profile();

    app(Refilament::class)->registerAuthRoutes();
});

it('serves every auth page under the panel path with the version header', function () {
    $this->get('/refilament/login', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/login')
        ->assertHeader('X-Inertia-Version');

    $this->get('/refilament/register', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/register');

    $this->get('/refilament/forgot-password', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/forgot-password');

    // The challenge page requires a challenged user in the session, mirroring
    // Fortify's TwoFactorAuthenticatedSessionController::create().
    $user = User::factory()->create();
    session(['login.id' => $user->getKey(), 'login.remember' => false]);

    $this->get('/refilament/two-factor-challenge', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/two-factor-challenge');
});

it('authenticates a visitor through the panel login page', function () {
    User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret-password']);

    $this->post('/refilament/login', [
        'email' => 'admin@example.com',
        'password' => 'secret-password',
    ])->assertRedirect('/refilament');

    $this->assertAuthenticated();
});

it('rejects invalid credentials on the panel login page', function () {
    User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret-password']);

    $this->from('/refilament/login')->post('/refilament/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertRedirect('/refilament/login');

    $this->assertGuest();
});

it('registers and authenticates a new user', function () {
    // No action binding — the package's first-party CreateNewUser default
    // (bound when registration is enabled and the contract is unbound)
    // creates the user through the auth provider's model.
    $this->post('/refilament/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertRedirect('/refilament');

    $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    $this->assertAuthenticated();
});

it('sends a reset link and resets the password', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    // Fortify's success response redirects back(), so the referer matters.
    $this->from('/refilament/forgot-password')
        ->post('/refilament/forgot-password', ['email' => 'admin@example.com'])
        ->assertRedirect('/refilament/forgot-password')
        ->assertSessionHas('status');

    $token = Password::broker()->createToken($user);

    $this->get("/refilament/reset-password/{$token}?email=admin@example.com", ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/reset-password')
        ->assertJsonPath('props.token', $token)
        ->assertJsonPath('props.email', 'admin@example.com');

    // Fortify's PasswordResetResponse redirects to fortify.home (via the
    // redirects null-coalesce) and does NOT auto-authenticate — the visitor
    // returns to the panel and logs in with the new password.
    $this->post('/refilament/reset-password', [
        'token' => $token,
        'email' => 'admin@example.com',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect('/refilament');

    $this->assertGuest();
    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue();

    // The new password works for a real login.
    $this->post('/refilament/login', [
        'email' => 'admin@example.com',
        'password' => 'new-secret-password',
    ])->assertRedirect('/refilament');

    $this->assertAuthenticated();
});

it('prompts an unverified user to verify their email and verifies via the signed link', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)->get('/refilament/email/verify', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/verify-email');

    // The panel's routes live under panel-scoped names — Fortify's global
    // `verification.verify` here belongs to the testbench's own Fortify (the
    // app's copy at /email/verify), so the panel's signed link must use the
    // panel's name to reach the panel's route.
    $verificationUrl = URL::signedRoute('refilament.refilament.auth.email-verification.verify', [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('challenges a two-factor user at login and completes the challenge', function () {
    $provider = app(TwoFactorAuthenticationProvider::class);
    $secret = $provider->generateSecretKey();

    $user = User::factory()->create([
        'email' => 'two-factor@example.com',
        'password' => 'secret-password',
    ]);

    // The trait is what makes RedirectIfTwoFactorAuthenticatable treat this
    // user as a 2FA user; it stores the secret pre-encrypted (the trait
    // decrypts manually — no Eloquent 'encrypted' cast is involved).
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(RecoveryCode::generate())),
        'two_factor_confirmed_at' => now(),
    ])->save();

    // Password accepted, then bounced to the challenge page.
    $this->post('/refilament/login', [
        'email' => 'two-factor@example.com',
        'password' => 'secret-password',
    ])->assertRedirect('/refilament/two-factor-challenge');

    $this->assertGuest();

    $this->get('/refilament/two-factor-challenge', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/two-factor-challenge');

    // The provider has no OTP helper — the code comes from the Google2FA
    // engine the provider is constructed with (resolvable from the container).
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post('/refilament/two-factor-challenge', ['code' => $code])
        ->assertRedirect('/refilament');

    $this->assertAuthenticatedAs($user);
});

it('lets a consumer override an auth page with their own page class', function () {
    $custom = new class extends AuthPage
    {
        public static function getComponent(): string
        {
            return 'auth/custom-login';
        }

        public static function getPath(): string
        {
            return '/login';
        }
    };

    // The view closures capture the live panel singleton, so re-pointing the
    // login page is reflected on the already-registered route.
    app(Refilament::class)->panel()->login($custom::class);

    $this->get('/refilament/login', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'auth/custom-login');
});

it('redirects the auth gate to the panel login page when it is enabled', function () {
    $this->get('/refilament', ['X-Inertia' => 'true'])
        ->assertRedirect('/refilament/login');

    $this->get('/refilament/posts', ['X-Inertia' => 'true'])
        ->assertRedirect('/refilament/login');
});

it('registers its auth routes under panel-scoped names, never Fortify\'s globals', function () {
    // The panel's names live in their own namespace...
    expect(route('refilament.refilament.auth.login'))->toBe(url('/refilament/login'));
    expect(route('refilament.refilament.auth.two-factor.login'))->toBe(url('/refilament/two-factor-challenge'));
    expect(route('refilament.refilament.auth.password.confirm'))->toBe(url('/refilament/user/confirm-password'));
    expect(route('refilament.refilament.auth.user-password.update'))->toBe(url('/refilament/user/password'));

    // ...while the global names the testbench's own Fortify registers keep
    // resolving to the app's routes — the collision that broke Wayfinder
    // builds and silently re-routed `route('login')` is gone. (The testbench
    // runs with fortify.views=false, so only Fortify's non-view POST routes
    // exist app-wide.)
    expect(route('login.store'))->toBe(url('/login'));
    expect(route('user-password.update'))->toBe(url('/user/password'));
    expect(route('two-factor.enable'))->toBe(url('/user/two-factor-authentication'));
    expect(route('password.email'))->toBe(url('/forgot-password'));
});

it('redirects an unauthenticated visitor to the panel login from the account routes', function () {
    // The authenticated surface (profile, password confirmation, 2FA
    // management) is gated by the panel's own Authenticate middleware, so an
    // unauthenticated hit lands on the panel's login page — not a bare 401
    // from the framework `auth` middleware's global-name redirect.
    $this->get('/refilament/user/profile')
        ->assertRedirect('/refilament/login');

    $this->get('/refilament/user/confirm-password')
        ->assertRedirect('/refilament/login');
});
