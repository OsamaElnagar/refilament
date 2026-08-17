<?php

declare(strict_types=1);

namespace Refilament\Refilament\Panel;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use LogicException;
use Refilament\Refilament\Auth\Pages\AuthPage;
use Refilament\Refilament\Auth\Pages\EditProfile;
use Refilament\Refilament\Auth\Pages\EmailVerificationPrompt;
use Refilament\Refilament\Auth\Pages\Login;
use Refilament\Refilament\Auth\Pages\Register;
use Refilament\Refilament\Auth\Pages\RequestPasswordReset;
use Refilament\Refilament\Auth\Pages\ResetPassword;
use Refilament\Refilament\Auth\Pages\TwoFactorChallenge;
use Refilament\Refilament\Auth\Pages\TwoFactorSettings;
use Refilament\Refilament\Clusters;
use Refilament\Refilament\Navigation\NavigationGroup;
use Refilament\Refilament\Navigation\NavigationItem;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use Refilament\Refilament\Support\Enums\PanelsRenderHook;
use Refilament\Refilament\Widgets\Widget;
use ReflectionClass;

/**
 * The package's single panel config (slice 1.9 — docs/ROADMAP.md "1.9 Panel
 * shell"), mirroring the config surface of Filament's Panel but describing
 * pure data served to the frontend shell — there is no Livewire component to
 * configure. The panel collects the sidebar navigation: one item per
 * navigation-registered resource (plus one per opt-in custom resource page,
 * plus any module-added items/groups), bucketed into groups by each item's
 * group name.
 *
 * Active nav state is derived on the client from the current URL; nothing here
 * remembers state between requests. `colors` become CSS custom properties the
 * shell applies (primary theming); `widgets` are the classes the dashboard
 * route renders (built per request, so their stat closures never cross the
 * wire). Collapsible-group UI and the auth gate remain later slices of 1.9.
 */
class Panel
{
    use EvaluatesClosures;

    final public function __construct() {}

    protected string $id = 'refilament';

    /**
     * The panel's URL prefix — everything the panel serves (the dashboard,
     * every resource page, the standalone pages and the typed endpoints)
     * lives under "/{path}". Mirrors Filament's `Panel::path('admin')`: the
     * first identity decision a consumer makes alongside `id()`. Kept as a
     * bare segment (no slashes) so route registration and every URL built
     * here agree on one shape.
     */
    protected string $path = 'refilament';

    protected string $brandName = 'Refilament';

    /**
     * A brand logo beside the brand name — a URL, or a closure resolving to
     * one (mirrors Heaven's closure `brandLogo()`, minus the Htmlable). The
     * React shell renders it as the sidebar / top-nav mark.
     *
     * @var string|Closure(): string|null
     */
    protected mixed $brandLogo = null;

    protected bool $sidebarCollapsible = false;

    /**
     * Render the navigation in a top bar instead of the sidebar (mirrors
     * Filament's `topNavigation()`), driven by the shell contract.
     */
    protected bool $topNavigation = false;

    /**
     * @var array<int, class-string<resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, NavigationItem>
     */
    protected array $navigationItems = [];

    /**
     * @var array<int, NavigationGroup>
     */
    protected array $navigationGroups = [];

    /**
     * CSS custom-property values keyed by their suffix ('primary' => hex) the
     * shell applies to theme the brand (docs/ROADMAP.md, "1.9 ->colors()").
     *
     * @var array<string, string>
     */
    protected array $colors = [];

    /**
     * Extension points where the shell renders consumer-provided UI (slice
     * B1) — mirrors Filament's `renderHook(PanelsRenderHook::...)`. Each entry
     * names a shell slot (a `PanelsRenderHook` value) and the HTML the shell
     * injects there. The value is a plain HTML string or a closure returning
     * one (commonly a Blade view: `fn (): string => view('partials.foo')->render()`),
     * evaluated to its final string per request — exactly Filament's model,
     * so a consumer injects their own Blade/HTML with no separate JS bundle.
     * Declaring a hook here is what arms it: the shell only renders slots the
     * server has enabled.
     *
     * @var array<string, string|Closure>
     */
    protected array $renderHooks = [];

    /**
     * @var array<int, class-string<Widget>>
     */
    protected array $widgets = [];

    /**
     * Standalone panel pages — slices of behavior not tied to a resource,
     * e.g. a settings or about page — that belong to this panel
     * (docs/ROADMAP.md, "1.9 ->pages([...])"). They extend Pages\Page and
     * are served by the shared PanelPageController route; opt-in pages that
     * set shouldRegisterNavigation() also surface in the sidebar.
     *
     * @var array<int, class-string<Page>>
     */
    protected array $pages = [];

    /**
     * Registered cluster classes (the page-clusters slice) — containers that
     * group pages and resources under one sidebar entry.
     *
     * @var array<int, class-string<Clusters\Cluster>>
     */
    protected array $clusters = [];

    /**
     * The brand's target URL (the dashboard). Null derives from the panel's
     * `path` — the default, so changing the path moves the brand link with
     * it; an explicit value always wins.
     */
    protected ?string $dashboardUrl = null;

    /**
     * Middleware applied to every panel route (the shell pages and the typed
     * endpoints) — mirrors Filament's `Panel::middleware()`. Defaults to an
     * empty list; the framework's `web` group (sessions + CSRF +
     * SubstituteBindings) is always applied around the panel routes, and this
     * list runs after it — a consumer adds e.g. `RateLimiter::class` or their
     * own middleware here. Pure config resolved at route registration, never
     * serialized across the wire.
     *
     * @var array<int, class-string|string>
     */
    protected array $middleware = [];

    /**
     * Whether the panel renders page breadcrumbs (slice 1.11), mirroring
     * Filament's `Panel::breadcrumbs()` — on by default, and a server-side
     * gate only: when off, the `breadcrumbs` key is omitted from every page
     * payload entirely (never shipped to the client). Pure config, not
     * serialized across the wire.
     */
    protected bool $hasBreadcrumbs = true;

    /**
     * Whether the shell renders the database-notifications bell (slice B3),
     * mirroring Filament's `Panel::databaseNotifications()`. The bell polls
     * the typed notifications endpoint for the unread count and latest rows,
     * and marks notifications read as the user dismisses them.
     */
    protected bool $databaseNotifications = false;

    /**
     * The bell's polling interval, Filament's '7s' / '150s' style. Defaults to
     * '30s' when notifications are enabled without an explicit interval.
     */
    protected ?string $notificationsPolling = null;

    /**
     * The auth guard the panel's access gate checks (slice 1.9 "auth gate").
     * Mirrors Filament's `Panel::authGuard()` — the guard the panel's
     * `Authenticate` middleware authenticates against before rendering any
     * shell page. Pure config: which guard to check is decided by the app,
     * not the request.
     */
    protected string $authGuard = 'web';

    /**
     * Where an unauthenticated visitor is redirected when the panel's access
     * gate is enabled (slice 1.9 "auth gate"). `null` (the default) keeps the
     * gate permissive — no login URL, no redirection, the workbench stays open.
     * When set alongside a configured auth middleware, an unauthenticated
     * request to a shell page is redirected here.
     */
    protected ?string $loginUrl = null;

    /**
     * Middleware applied to the panel's shell-rendering routes (the dashboard
     * and every resource page) — slice 1.9 "auth gate", mirroring Filament's
     * `Panel::authMiddleware()`. Defaults to an empty list, so the panel serves
     * every shell page openly; registering `Authenticate::class` here (plus a
     * `loginUrl()`) turns the gate on. The list is serialized into the route
     * definitions at boot, never across the wire.
     *
     * @var array<int, class-string>
     */
    protected array $authMiddleware = [];

    /**
     * The panel's first-party auth pages (docs/ROADMAP.md "1.9 auth pages") —
     * the page class each Fortify-backed auth route renders, mirroring
     * Filament's `Panel::login()` / `registration()` / `passwordReset()` /
     * `emailVerification()`. Null means the page is disabled (the default —
     * the panel ships with no auth pages, matching Filament). Setting a class
     * arms the route and makes `getLoginUrl()` default to the panel's own
     * page; a consumer overrides a page by passing their own class extending
     * `Auth\Pages\AuthPage`. Pure config, never serialized across the wire.
     *
     * @var class-string<AuthPage>|null
     */
    protected ?string $loginPage = null;

    /**
     * How the panel owns authentication (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md)
     * — `Standalone` (default: the panel registers its own Fortify-backed auth
     * routes under panel-scoped names) or `Shared` (the panel registers no auth
     * routes and the gate defers to the app's own `login` route). Opt-in via
     * `authMode()`; enabling any panel-owned auth page in shared mode throws.
     */
    protected AuthMode $authMode = AuthMode::Standalone;

    /**
     * @var class-string<AuthPage>|null
     */
    protected ?string $registrationPage = null;

    /**
     * @var class-string<AuthPage>|null
     */
    protected ?string $requestPasswordResetPage = null;

    /**
     * @var class-string<AuthPage>|null
     */
    protected ?string $resetPasswordPage = null;

    /**
     * @var class-string<AuthPage>|null
     */
    protected ?string $emailVerificationPage = null;

    /**
     * Whether Fortify's two-factor authentication is available to the panel
     * (the challenge page + the management endpoints). Off by default, like
     * Filament's multi-factor opt-in.
     */
    protected bool $twoFactorAuthentication = false;

    /**
     * The panel's profile page (Filament's `->profile()`) — where the
     * authenticated user updates their name/email/password and manages
     * two-factor authentication, served at /{{path}}/user/profile. Null means
     * disabled (the default). Setting a class arms the route; a consumer
     * overrides the page by passing their own class extending
     * `Auth\Pages\AuthPage`. Pure config, never serialized across the wire.
     *
     * @var class-string<AuthPage>|null
     */
    protected ?string $profilePage = null;

    public static function make(): static
    {
        return new static;
    }

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * The panel's URL prefix, stored bare ('admin', never '/admin').
     */
    public function path(string $path): static
    {
        $this->path = trim($path, '/');

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Build an absolute panel URL for a path relative to the panel's prefix
     * ('/{resource}/create' → '/refilament/{resource}/create'). Every URL the
     * panel or its resources hand to the shell goes through here, so a
     * consumer's `->path('admin')` moves the whole panel.
     */
    public function url(string $path = ''): string
    {
        $url = '/'.ltrim($this->getPath(), '/');

        if ($path !== '') {
            $url .= '/'.ltrim($path, '/');
        }

        return $url;
    }

    public function brandName(string $brandName): static
    {
        $this->brandName = $brandName;

        return $this;
    }

    /**
     * A brand logo rendered beside the brand name. Accepts a URL string or a
     * closure resolving to one (evaluated at serialization, never shipped).
     *
     * @param  string|Closure(): string|null  $logo
     */
    public function brandLogo(string|Closure|null $logo): static
    {
        $this->brandLogo = $logo;

        return $this;
    }

    public function getBrandLogo(): ?string
    {
        $logo = $this->evaluate($this->brandLogo);

        return is_string($logo) ? $logo : null;
    }

    /**
     * Render the navigation in a top bar instead of the sidebar.
     */
    public function topNavigation(bool $condition = true): static
    {
        $this->topNavigation = $condition;

        return $this;
    }

    public function isTopNavigation(): bool
    {
        return $this->topNavigation;
    }

    /**
     * The "<" method name mirrors Filament's Panel surface.
     */
    public function sidebarCollapsibleOnDesktop(bool $condition = true): static
    {
        $this->sidebarCollapsible = $condition;

        return $this;
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     */
    public function resources(array $resources): static
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * @param  array<int, NavigationItem>  $items
     */
    public function navigationItems(array $items): static
    {
        $this->navigationItems = $items;

        return $this;
    }

    /**
     * @param  array<int, NavigationGroup>  $groups
     */
    public function navigationGroups(array $groups): static
    {
        $this->navigationGroups = $groups;

        return $this;
    }

    /**
     * @param  array<string, string>  $colors  CSS variable suffix => value
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * Arm a shell render hook (slice B1): the named slot renders the given
     * HTML wherever the shell places it. Mirrors Filament's
     * `renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): string => ...)` —
     * pass a `PanelsRenderHook` case for any built-in slot, or a raw string to
     * define a custom slot your own shell mounts. The value is the HTML (or a
     * closure returning it — commonly `view('...')->render()`) injected into
     * the slot; a consumer provides their own Blade/HTML, no separate JS.
     */
    public function renderHook(PanelsRenderHook|string $slot, Closure|string $content): static
    {
        $this->renderHooks[$slot instanceof PanelsRenderHook ? $slot->value : $slot] = $content;

        return $this;
    }

    /**
     * The armed render hooks resolved to final HTML (closures evaluated per
     * request). Keys are slot names, values the markup the shell injects.
     *
     * @return array<string, string>
     */
    public function getRenderHooks(): array
    {
        return array_map(
            fn (Closure|string $content): string => (string) $this->evaluate($content),
            $this->renderHooks,
        );
    }

    /**
     * @param  array<int, class-string<Widget>>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    /**
     * Explicitly register standalone panel pages (slice 1.9 "->pages([...])"),
     * mirroring Filament's `Panel::pages()`. Pages are appended to any already
     * registered, deduplicated in getPages().
     *
     * @param  array<int, class-string<Page>>  $pages
     */
    public function pages(array $pages): static
    {
        foreach ($pages as $page) {
            if ($page::getSlug() === '') {
                throw new LogicException("Page [{$page}] must resolve a non-empty slug.");
            }

            $this->pages[] = $page;
        }

        return $this;
    }

    /**
     * Auto-discover standalone panel pages in a directory (slice 1.9),
     * mirroring Filament's `Panel::discoverPages($in, $for)`. Every non-abstract
     * class in `$in` that extends Pages\Page is registered. No-op when the
     * directory doesn't exist, so a documented but not-yet-created folder is
     * not an error.
     */
    public function discoverPages(string $in, string $for): static
    {
        if (! is_dir($in)) {
            return $this;
        }

        $filesystem = app(Filesystem::class);

        $known = array_flip($this->pages);

        foreach ($filesystem->allFiles($in) as $file) {
            $class = $for.'\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (isset($known[$class]) || ! class_exists($class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (! is_subclass_of($class, Page::class)) {
                continue;
            }

            $this->pages[] = $class;
            $known[$class] = true;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<Page>>
     */
    public function getPages(): array
    {
        return array_values(array_unique($this->pages));
    }

    /**
     * Explicitly register clusters (the page-clusters slice), mirroring
     * Filament's `Panel::clusters()`. Deduplicated in getClusters().
     *
     * @param  array<int, class-string<Clusters\Cluster>>  $clusters
     */
    public function clusters(array $clusters): static
    {
        foreach ($clusters as $cluster) {
            $this->clusters[] = $cluster;
        }

        return $this;
    }

    /**
     * Auto-discover cluster classes in a directory (the page-clusters slice),
     * mirroring Filament's `Panel::discoverClusters($in, $for)`. No-op when
     * the directory doesn't exist.
     */
    public function discoverClusters(string $in, string $for): static
    {
        if (! is_dir($in)) {
            return $this;
        }

        $filesystem = app(Filesystem::class);

        $known = array_flip($this->clusters);

        foreach ($filesystem->allFiles($in) as $file) {
            $class = $for.'\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (isset($known[$class]) || ! class_exists($class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (! is_subclass_of($class, Clusters\Cluster::class)) {
                continue;
            }

            $this->clusters[] = $class;
            $known[$class] = true;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<Clusters\Cluster>>
     */
    public function getClusters(): array
    {
        return array_values(array_unique($this->clusters));
    }

    public function dashboardUrl(?string $url): static
    {
        $this->dashboardUrl = $url;

        return $this;
    }

    public function authGuard(string $guard): static
    {
        $this->authGuard = $guard;

        return $this;
    }

    public function loginUrl(?string $url): static
    {
        $this->loginUrl = $url;

        return $this;
    }

    /**
     * How the panel owns authentication — `AuthMode::Standalone` (default) or
     * `AuthMode::Shared` (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md). In
     * shared mode the panel registers no auth routes of its own and the auth
     * gate defers to the app's `login` route; every panel-owned auth page
     * (`->login()`, `->registration()`, ...) then throws, so the choice is
     * loud rather than silently ignored.
     */
    public function authMode(AuthMode $authMode): static
    {
        if ($authMode->isShared() && $this->hasAuthPages()) {
            throw new LogicException(
                'Cannot switch the panel to shared auth mode while a panel-owned auth page is enabled. '.PHP_EOL.
                'In shared mode the panel registers no auth routes — use the app\'s own auth '.PHP_EOL.
                '(login, register, password reset, profile) instead, and remove the '.PHP_EOL.
                '->login() / ->registration() / ->passwordReset() / ->emailVerification() / '.PHP_EOL.
                '->twoFactorAuthentication() / ->profile() calls from the panel provider.',
            );
        }

        $this->authMode = $authMode;

        return $this;
    }

    public function getAuthMode(): AuthMode
    {
        return $this->authMode;
    }

    /**
     * Reject a panel-owned auth page in shared auth mode — the panel owns no
     * auth surface there, so enabling one is a misconfiguration and should be
     * loud. `null` (disabling a page) is always allowed.
     */
    protected function ensureStandaloneAuth(string $method): void
    {
        if ($this->authMode->isShared()) {
            throw new LogicException(
                "Cannot call Panel::{$method}() in shared auth mode — the panel registers no ".PHP_EOL.
                'auth routes of its own there. Use the app\'s own auth pages instead, or switch to '.PHP_EOL.
                'AuthMode::Standalone with ->authMode(AuthMode::Standalone) to own the auth surface.',
            );
        }
    }

    /**
     * Enable the panel's login page — mirroring Filament's `Panel::login()`, with
     * `Login::class` as the default page and `null` disabling it. Enabling it
     * registers `/{{path}}/login` and makes the auth gate's default redirect the
     * panel's own login page.
     *
     * @param  class-string<AuthPage>|null  $page
     */
    public function login(?string $page = Login::class): static
    {
        if ($page !== null) {
            $this->ensureStandaloneAuth('login');
        }

        $this->loginPage = $page;

        return $this;
    }

    /**
     * Enable the registration page (Filament's `Panel::registration()`).
     *
     * @param  class-string<AuthPage>|null  $page
     */
    public function registration(?string $page = Register::class): static
    {
        if ($page !== null) {
            $this->ensureStandaloneAuth('registration');
        }

        $this->registrationPage = $page;

        return $this;
    }

    /**
     * Enable the forgot-password + reset-password pages (Filament's
     * `Panel::passwordReset()`), backed by Laravel's password broker through
     * Fortify.
     *
     * @param  class-string<AuthPage>|null  $requestPage
     * @param  class-string<AuthPage>|null  $resetPage
     */
    public function passwordReset(
        ?string $requestPage = RequestPasswordReset::class,
        ?string $resetPage = ResetPassword::class,
    ): static {
        if ($requestPage !== null || $resetPage !== null) {
            $this->ensureStandaloneAuth('passwordReset');
        }

        $this->requestPasswordResetPage = $requestPage;
        $this->resetPasswordPage = $resetPage;

        return $this;
    }

    /**
     * Enable the email-verification prompt page (Filament's
     * `Panel::emailVerification()`), rendered to verified-required users and
     * backed by Fortify's verification routes.
     *
     * @param  class-string<AuthPage>|null  $promptPage
     */
    public function emailVerification(?string $promptPage = EmailVerificationPrompt::class): static
    {
        if ($promptPage !== null) {
            $this->ensureStandaloneAuth('emailVerification');
        }

        $this->emailVerificationPage = $promptPage;

        return $this;
    }

    /**
     * Enable Fortify's two-factor authentication for the panel (the challenge
     * page reached after a 2FA-enabled login, plus the management endpoints).
     * Off by default.
     */
    public function twoFactorAuthentication(bool $condition = true): static
    {
        if ($condition) {
            $this->ensureStandaloneAuth('twoFactorAuthentication');
        }

        $this->twoFactorAuthentication = $condition;

        return $this;
    }

    /**
     * Enable the panel's profile page (Filament's `Panel::profile()`), where
     * the authenticated user updates their name/email/password and manages
     * two-factor authentication. Served at /{{path}}/user/profile behind the
     * panel's auth guard.
     *
     * @param  class-string<AuthPage>|null  $page
     */
    public function profile(?string $page = EditProfile::class): static
    {
        if ($page !== null) {
            $this->ensureStandaloneAuth('profile');
        }

        $this->profilePage = $page;

        return $this;
    }

    public function hasLogin(): bool
    {
        return $this->loginPage !== null;
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getLoginPage(): string
    {
        return $this->loginPage ?? Login::class;
    }

    public function hasRegistration(): bool
    {
        return $this->registrationPage !== null;
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getRegistrationPage(): string
    {
        return $this->registrationPage ?? Register::class;
    }

    public function hasPasswordReset(): bool
    {
        return $this->requestPasswordResetPage !== null || $this->resetPasswordPage !== null;
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getRequestPasswordResetPage(): string
    {
        return $this->requestPasswordResetPage ?? RequestPasswordReset::class;
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getResetPasswordPage(): string
    {
        return $this->resetPasswordPage ?? ResetPassword::class;
    }

    public function hasEmailVerification(): bool
    {
        return $this->emailVerificationPage !== null;
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getEmailVerificationPage(): string
    {
        return $this->emailVerificationPage ?? EmailVerificationPrompt::class;
    }

    public function hasTwoFactorAuthentication(): bool
    {
        return $this->twoFactorAuthentication;
    }

    public function hasProfile(): bool
    {
        return $this->profilePage !== null;
    }

    /**
     * Whether the panel mounts any of the first-party auth routes (login,
     * registration, password reset, email verification, profile) — the same
     * condition Refilament::registerAuthRoutes() uses to decide whether the
     * auth route file is required at all. The shell reads this indirectly:
     * the logout URL is only shared when the routes behind it exist.
     */
    public function hasAuthPages(): bool
    {
        return $this->hasLogin()
            || $this->hasRegistration()
            || $this->hasPasswordReset()
            || $this->hasEmailVerification()
            || $this->hasProfile();
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getProfilePage(): string
    {
        return $this->profilePage ?? EditProfile::class;
    }

    /**
     * @return class-string<AuthPage>
     */
    public function getTwoFactorChallengePage(): string
    {
        return TwoFactorChallenge::class;
    }

    /**
     * The page class the panel renders for two-factor management (the
     * "enable/disable, QR code, recovery codes" UI at /{{path}}/user/two-factor).
     * Available only when twoFactorAuthentication() is enabled.
     *
     * @return class-string<AuthPage>
     */
    public function getTwoFactorSettingsPage(): string
    {
        return TwoFactorSettings::class;
    }

    /**
     * @param  array<int, class-string>  $middleware
     */
    public function authMiddleware(array $middleware): static
    {
        $this->authMiddleware = $middleware;

        return $this;
    }

    /**
     * @param  array<int, class-string|string>  $middleware
     */
    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * @return array<int, class-string|string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Toggle page breadcrumbs panel-wide (slice 1.11) — mirrors Filament's
     * `Panel::breadcrumbs()`, on by default. When off, the `breadcrumbs` key
     * is omitted from every page payload; a page's own `getBreadcrumbs()`
     * override never ships either, since the gate runs before serialization.
     */
    public function breadcrumbs(bool $condition = true): static
    {
        $this->hasBreadcrumbs = $condition;

        return $this;
    }

    public function hasBreadcrumbs(): bool
    {
        return $this->hasBreadcrumbs;
    }

    /**
     * Enable the shell's database-notifications bell (slice B3) — mirrors
     * Filament's `Panel::databaseNotifications()`. The bell reads the
     * authenticated user's notifications through the typed endpoint.
     */
    public function databaseNotifications(bool $condition = true): static
    {
        $this->databaseNotifications = $condition;

        return $this;
    }

    /**
     * The bell's polling interval, in Filament's '7s' / '150s' style. Falls
     * back to '30s' when unset (Filament's default).
     */
    public function databaseNotificationsPolling(?string $interval): static
    {
        $this->notificationsPolling = $interval;

        return $this;
    }

    public function hasDatabaseNotifications(): bool
    {
        return $this->databaseNotifications;
    }

    public function getNotificationsPolling(): ?string
    {
        return $this->notificationsPolling;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<int, class-string<resource>>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function isSidebarCollapsible(): bool
    {
        return $this->sidebarCollapsible;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    /**
     * @return array<int, class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    public function getDashboardUrl(): string
    {
        return $this->dashboardUrl ?? $this->url();
    }

    public function getAuthGuard(): string
    {
        return $this->authGuard;
    }

    /**
     * Where the auth gate sends an unauthenticated visitor. An explicit
     * `->loginUrl(...)` always wins; otherwise, once the panel's login page is
     * enabled, the panel's own login route (`/{{path}}/login`) — so enabling
     * `->login()` makes the whole gate point at the first-party page. In
     * shared auth mode the gate defers to the app's own `login` route (the
     * panel registers none of its own); the redirect goes through Laravel's
     * `guest()` flow, so the visitor's intended panel page is preserved in the
     * session and the app's post-login `intended()` redirect lands them back.
     * Null (the default) keeps the gate permissive.
     */
    public function getLoginUrl(): ?string
    {
        if ($this->loginUrl !== null) {
            return $this->loginUrl;
        }

        if ($this->authMode->isShared()) {
            return Route::has('login') ? route('login') : '/';
        }

        if ($this->hasLogin()) {
            return $this->url('/login');
        }

        return null;
    }

    /**
     * @return array<int, class-string>
     */
    public function getAuthMiddleware(): array
    {
        return $this->authMiddleware;
    }

    /**
     * Build the sidebar navigation contract: groups (ordered) plus any items
     * that belong to no group. Each registered group keeps its label/icon/
     * collapse configuration; the items assigned to it come from the app nav
     * items whose `group()` matches — a group with no members still renders
     * as a heading, mirroring Filament.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $items = array_merge(
            $this->resourceNavigationItems(),
            $this->panelPageNavigationItems(),
            $this->navigationItems,
        );

        // Clusters (the page-clusters slice) group their components under one
        // sidebar entry: each cluster becomes a parent item whose children are
        // the clustered components' own nav items, and those components drop
        // out of the top-level list. The cluster item keeps the cluster's
        // label/icon/sort and links to its redirect URL.
        $clustered = [];

        foreach ($this->getClusters() as $clusterClass) {
            foreach ($clusterClass::getClusteredComponents() as $component) {
                $clustered[$component] = $clusterClass;
            }
        }

        $clusterItems = [];

        foreach ($items as $item) {
            $clusterClass = $clustered[$item->getKey()] ?? null;

            if ($clusterClass === null) {
                continue;
            }

            $clusterItems[$clusterClass] ??= NavigationItem::make($clusterClass::getNavigationLabel())
                ->key($clusterClass)
                ->url($this->url('/'.$clusterClass::getSlug()))
                ->group($clusterClass::getNavigationGroup())
                ->sort($clusterClass::getNavigationSort())
                ->icon($clusterClass::getNavigationIcon());

            $clusterItems[$clusterClass]->childItems([
                ...$clusterItems[$clusterClass]->getChildItems(),
                $item,
            ]);
        }

        $items = array_values(array_filter(
            $items,
            static fn (NavigationItem $item): bool => ! isset($clustered[$item->getKey()]),
        ));

        $items = array_merge($items, array_values($clusterItems));

        /** @var array<string, NavigationGroup> $groups */
        $groups = [];

        foreach ($this->navigationGroups as $group) {
            $groups[$group->getLabel()] = $group;
        }

        /** @var array<string, array<int, NavigationItem>> $bucketed */
        $bucketed = [];

        /** @var array<int, NavigationItem> $ungrouped */
        $ungrouped = [];

        foreach ($items as $item) {
            $group = $item->getGroup();

            if ($group !== null) {
                $groups[$group] ??= NavigationGroup::make($group);
                $bucketed[$group][] = $item;
            } else {
                $ungrouped[] = $item;
            }
        }

        $groupData = [];

        foreach ($groups as $label => $group) {
            $members = $bucketed[$label] ?? [];
            usort($members, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

            $groupData[] = [
                ...(array) $group->toArray(),
                'items' => array_map(
                    static fn (NavigationItem $item): array => $item->toArray(),
                    $members,
                ),
            ];
        }

        usort($groupData, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);
        usort($ungrouped, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

        $brandLogo = $this->getBrandLogo();

        return [
            'id' => $this->id,
            'path' => $this->getPath(),
            'brandName' => $this->brandName,
            ...($brandLogo !== null ? ['brandLogo' => $brandLogo] : []),
            'sidebarCollapsible' => $this->sidebarCollapsible,
            'topNavigation' => $this->topNavigation,
            'dashboardUrl' => $this->getDashboardUrl(),
            ...($this->colors !== [] ? ['colors' => $this->colors] : []),
            ...($this->renderHooks !== [] ? ['renderHooks' => $this->getRenderHooks()] : []),
            ...($this->databaseNotifications ? ['notifications' => ['polling' => $this->notificationsPolling ?? '30s']] : []),
            // Account pages the shell's user menu links to (slice 1.9 "user
            // menu"). Each URL is shared only when the feature behind it is
            // enabled — the menu renders a link only when the server says the
            // route exists. The logout route is mounted whenever any auth page
            // is, so its URL follows the same gate. In shared auth mode the
            // panel owns none of these: profile and 2FA belong to the app, and
            // the shell's logout points at the app's own `logout` route (the
            // app's Fortify provides it — shared mode assumes it does).
            ...($this->hasProfile() ? ['profileUrl' => $this->url('/user/profile')] : []),
            ...($this->hasTwoFactorAuthentication() ? ['twoFactorUrl' => $this->url('/user/two-factor')] : []),
            ...($this->getAuthMode()->isShared()
                ? (Route::has('logout') ? ['logoutUrl' => route('logout')] : [])
                : ($this->hasAuthPages() ? ['logoutUrl' => $this->url('/logout')] : [])),
            'groups' => $groupData,
            'items' => array_map(
                static fn (NavigationItem $item): array => $item->toArray(),
                $ungrouped,
            ),
        ];
    }

    /**
     * One navigation item per navigation-registered resource, pointing at the
     * resource's list page, plus one per opt-in custom resource page (a page
     * in the resource's getPages() map that is not one of the built-in
     * list/create/edit/view pages and whose shouldRegisterNavigation() is
     * true). A custom page inherits the resource's group and icon unless it
     * overrides them.
     *
     * @return array<int, NavigationItem>
     */
    protected function resourceNavigationItems(): array
    {
        $items = [];

        foreach ($this->resources as $resource) {
            if (! $resource::shouldRegisterNavigation()) {
                continue;
            }

            // A resource the current user cannot access (slice 4.1) is hidden
            // from the sidebar — same as Filament, whose nav reflects per-user
            // policy. With no policy the default allows access, so a fresh
            // install lists everything.
            if (! $resource::canAccess()) {
                continue;
            }

            $items[] = NavigationItem::make($resource::getNavigationLabel())
                ->key($resource)
                ->url($resource::getNavigationUrl())
                ->group($resource::getNavigationGroup())
                ->sort($resource::getNavigationSort())
                ->icon($resource::getNavigationIcon());

            foreach ($resource::getPages() as $pageName => $registration) {
                // Only custom pages (not the built-in list/create/edit/view)
                // can register their own nav item — the built-ins are already
                // surfaced by the resource's own nav item above.
                if (in_array($pageName, ['index', 'create', 'edit', 'view'], true)) {
                    continue;
                }

                $page = $registration->getPage();

                if (! $page::shouldRegisterNavigation()) {
                    continue;
                }

                $items[] = NavigationItem::make($page::getNavigationLabel())
                    ->key($page)
                    ->url($this->url('/'.$resource::getTableId().$page::getRoutePath()))
                    ->group($page::getNavigationGroup() ?? $resource::getNavigationGroup())
                    ->sort($page::getNavigationSort())
                    ->icon($page::getNavigationIcon() ?? $resource::getNavigationIcon());
            }
        }

        return $items;
    }

    /**
     * One navigation item per opt-in standalone panel page (slice 1.9
     * "->pages([...])"). A page surfaces in the sidebar only when its
     * shouldRegisterNavigation() is true (the default is false, mirroring
     * Filament, where most pages don't appear). Its URL is the shared
     * page route under the panel's slug.
     *
     * @return array<int, NavigationItem>
     */
    protected function panelPageNavigationItems(): array
    {
        $items = [];

        foreach ($this->getPages() as $page) {
            if (! $page::shouldRegisterNavigation()) {
                continue;
            }

            $items[] = NavigationItem::make($page::getNavigationLabel())
                ->key($page)
                // A clustered page's nav URL carries the cluster prefix (the
                // page-clusters slice).
                ->url($this->url('/'.$page::getSlugPath()))
                ->group($page::getNavigationGroup())
                ->sort($page::getNavigationSort())
                ->icon($page::getNavigationIcon());
        }

        return $items;
    }
}
