<?php

declare(strict_types=1);

namespace Refilament\Refilament;

use Closure;
use FilesystemIterator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\PasskeysServiceProvider;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Refilament\Refilament\Auth\Actions\CreateNewUser;
use Refilament\Refilament\Auth\Actions\ResetUserPassword;
use Refilament\Refilament\Auth\Actions\UpdateUserPassword;
use Refilament\Refilament\Auth\Actions\UpdateUserProfileInformation;
use Refilament\Refilament\Auth\Pages\ConfirmPassword;
use Refilament\Refilament\Http\Controllers\ClusterRedirectController;
use Refilament\Refilament\Http\Controllers\PanelPageController;
use Refilament\Refilament\Http\Middleware\AppendInertiaVersion;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Pages\Page;
use Refilament\Refilament\Panel\Panel;
use Refilament\Refilament\Resources\RelationManagers\RelationManager;
use Refilament\Refilament\Resources\Resource;
use Refilament\Refilament\Schemas\Schema;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;
use Refilament\Refilament\Tables\Table;
use Refilament\Refilament\Widgets\Widget;

class Refilament
{
    use EvaluatesClosures;

    /**
     * Schema resolvers keyed by the schema id the client sends with
     * resolve-options requests (docs/CONTRACT.md, "Options"). Resolvers may
     * return null (a page-form resolver whose page declares no form at
     * resolution time) — resolveSchema() already handles null.
     *
     * @var array<string, Closure(): ?Schema>
     */
    protected array $schemaResolvers = [];

    /**
     * Table resolvers keyed by the table id the client requests through the
     * table index endpoint (docs/CONTRACT.md, "Tables").
     *
     * @var array<string, Closure(): Table>
     */
    protected array $tableResolvers = [];

    /**
     * Discovered resource classes keyed by their table id, in discovery
     * order. The package auto-registers the list and create page routes from
     * this map (docs/ARCHITECTURE.md, "Resources").
     *
     * @var array<string, class-string<resource>>
     */
    protected array $resourceClasses = [];

    /**
     * Lazily-built panel config (slice 1.9 — docs/ROADMAP.md "1.9 Panel
     * shell"), assembled from the discovered resources on first access so it
     * reflects any resources registered earlier in the same request. The
     * cache is invalidated when a consumer panel provider registers
     * (`registerPanel`) — that happens during provider *registration*, which
     * for a consumer app runs after the package's own providers have already
     * booted, so a panel built during package boot must not stick around.
     */
    protected ?Panel $panel = null;

    /**
     * The consumer's panel factory — the closure a `PanelProvider`'s
     * `register()` hands over (mirroring Filament's `registerPanel(fn ...)`).
     * Null until a provider registers, which is the workbench / config-driven
     * default mode. The factory receives the config-seeded panel and returns
     * the consumer's override.
     *
     * @var (Closure(Panel): Panel)|null
     */
    protected ?Closure $panelFactory = null;

    /**
     * Discovered relation manager classes, keyed by their parent resource's
     * table id and then by the relationship name they host (slice 1.8). The
     * scoped relation endpoint resolves a manager by these two keys and
     * rebuilds the owner-scoped query from the parent on every request.
     *
     * @var array<string, array<string, class-string<RelationManager>>>
     */
    protected array $relationManagers = [];

    /**
     * Widget resolvers keyed by the widget id the client requests through the
     * typed widget data endpoint (slice 3.2 — docs/CONTRACT.md, "Widgets").
     * The closure rebuilds the widget per request (filters + data closures
     * included), mirroring registerSchemaResolver/registerTable.
     *
     * @var array<string, Closure(): Widget>
     */
    protected array $widgetResolvers = [];

    /**
     * Discovered cluster classes, keyed by class (the page-clusters slice).
     * A cluster groups pages and resources under one sidebar entry; pages /
     * resources declare it via their `$cluster` property.
     *
     * @var array<string, class-string<Clusters\Cluster>>
     */
    protected array $clusterClasses = [];

    /**
     * Register the resolver for a schema document, keyed by its id.
     *
     * The closure must return the live schema definition (including any
     * server-side option resolvers) — never a serialized array, since
     * closures cannot survive serialization. Nullable for page-form
     * resolvers (a page's form is resolved fresh per request and a page
     * declaring none resolves null); resolveSchema() already handles null.
     *
     * @param  Closure(): ?Schema  $resolver
     */
    public function registerSchemaResolver(string $key, Closure $resolver): static
    {
        $this->schemaResolvers[$key] = $resolver;

        return $this;
    }

    public function resolveSchema(string $key): ?Schema
    {
        return $this->evaluate($this->schemaResolvers[$key] ?? null);
    }

    /**
     * Register the resolver for a table definition, keyed by its id.
     *
     * @param  Closure(): Table  $resolver
     */
    public function registerTable(string $key, Closure $resolver): static
    {
        $this->tableResolvers[$key] = $resolver;

        return $this;
    }

    public function resolveTable(string $key): ?Table
    {
        return $this->evaluate($this->tableResolvers[$key] ?? null);
    }

    /**
     * Register the resolver for a widget, keyed by its id (the kebab widget
     * class basename, or a custom key). The closure must return the live
     * widget instance — filters and data closures included, since a widget's
     * data can only re-resolve server-side (never survive serialization).
     *
     * @param  Closure(): Widget  $resolver
     */
    public function registerWidgetResolver(string $key, Closure $resolver): static
    {
        $this->widgetResolvers[$key] = $resolver;

        return $this;
    }

    /**
     * The widget instance registered under a widget id, if any.
     */
    public function resolveWidget(string $key): ?Widget
    {
        return $this->evaluate($this->widgetResolvers[$key] ?? null);
    }

    /**
     * Register every Resource class found in a directory — including nested
     * folders — under its table and form ids (docs/ARCHITECTURE.md,
     * "Resources"). Mirrors Filament's panel resource discovery; a resource
     * opts out via its isDiscovered(). The namespace is derived from the
     * file's path relative to the scanned root, so a self-contained
     * per-resource folder (`Resources/Posts/PostResource.php` →
     * `App\Refilament\Resources\Posts\PostResource`) resolves its class
     * without manual registration.
     */
    public function registerResourcesFromDirectory(string $path, string $namespace): static
    {
        if (! is_dir($path)) {
            return $this;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Resource.php')) {
                continue;
            }

            // PSR-4 maps the folder structure under the scan root onto the
            // namespace — `Resources/Posts/PostResource.php` gets
            // `App\Refilament\Resources\Posts`.
            $relative = substr($file->getPath(), strlen($path) + 1);
            $class = rtrim($namespace, '\\')
                .'\\'.($relative !== '' ? str_replace(DIRECTORY_SEPARATOR, '\\', $relative).'\\' : '')
                .basename($file->getFilename(), '.php');

            /** @var class-string<resource> $class */
            if (! is_subclass_of($class, Resource::class)) {
                continue;
            }

            $this->registerResources($class);
        }

        return $this;
    }

    /**
     * Register every Cluster class found in a directory (the page-clusters
     * slice), mirroring the resource discovery: the namespace is derived
     * from the file's path relative to the scanned root, and abstract or
     * non-Cluster classes are skipped.
     */
    public function registerClustersFromDirectory(string $path, string $namespace): static
    {
        if (! is_dir($path)) {
            return $this;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $relative = substr($file->getPath(), strlen($path) + 1);
            $class = rtrim($namespace, '\\')
                .'\\'.($relative !== '' ? str_replace(DIRECTORY_SEPARATOR, '\\', $relative).'\\' : '')
                .basename($file->getFilename(), '.php');

            if (! is_subclass_of($class, Clusters\Cluster::class)) {
                continue;
            }

            $this->registerCluster($class);
        }

        return $this;
    }

    /**
     * Register one cluster class (the page-clusters slice). A cluster must
     * extend the Cluster base; duplicates are ignored.
     *
     * @param  class-string<Clusters\Cluster>  $class
     */
    public function registerCluster(string $class): static
    {
        if (! is_subclass_of($class, Clusters\Cluster::class)) {
            throw new LogicException("Cluster [{$class}] must extend [".Clusters\Cluster::class.'].');
        }

        $this->clusterClasses[$class] = $class;

        return $this;
    }

    /**
     * Every registered cluster class, in registration order — the explicit
     * registerCluster() registry merged with the panel's (which the
     * config-seeded discovery populates), so route registration and the
     * redirect controller see every cluster either way.
     *
     * @return array<int, class-string<Clusters\Cluster>>
     */
    public function getClusters(): array
    {
        return array_values(array_unique([
            ...$this->clusterClasses,
            ...$this->panel()->getClusters(),
        ]));
    }

    /**
     * The cluster class registered under a cluster slug, if any.
     *
     * @return class-string<Clusters\Cluster>|null
     */
    public function getClusterClass(string $slug): ?string
    {
        foreach ($this->getClusters() as $class) {
            if ($class::getSlug() === $slug) {
                return $class;
            }
        }

        return null;
    }

    /**
     * The pages and resources that declared a cluster as their `$cluster`
     * (the page-clusters slice) — resources first, then panel pages, in
     * registration order. This is what a cluster groups in the sidebar and
     * redirects to.
     *
     * @return array<int, class-string>
     */
    public function getClusteredComponents(string $cluster): array
    {
        $components = [];

        foreach ($this->getResources() as $resource) {
            if ($resource::getCluster() === $cluster) {
                $components[] = $resource;
            }
        }

        foreach ($this->panel()->getPages() as $page) {
            if ($page::getCluster() === $cluster) {
                $components[] = $page;
            }
        }

        return $components;
    }

    /**
     * Register one resource class's table and form under its ids.
     *
     * @param  class-string<resource>  $class
     */
    public function registerResources(string $class): static
    {
        if (! $class::isDiscovered()) {
            return $this;
        }

        $tableId = $class::getTableId();

        if (isset($this->resourceClasses[$tableId])) {
            // Already discovered under this id — skip so the table/form
            // resolvers and the page routes never disagree about which
            // class wins a duplicate id.
            return $this;
        }

        $this->registerTable(
            $tableId,
            static fn (): Table => $class::table(new Table)
                // Record navigation (record navigation slice): the table's
                // URL resolver supplies per-row click targets and record
                // action URLs (ViewAction) straight from the resource's page
                // map + policy gates, so every resource table gets clickable
                // rows and working built-in record actions with zero consumer
                // configuration.
                ->urlUsing(static fn (string $page, mixed $record): ?string => $class::getRecordUrl($page, $record)),
        );

        // The resource's form carries its model as the create default —
        // `Schema::submit()` falls back to `$model::create($data)` when the
        // consumer declares no submitUsing() handler, so resource create
        // forms "just work" (docs/ROADMAP.md "2.6 Default create"). The
        // consumer's form() runs first, so an explicit submitUsing() always
        // wins over the model default.
        $this->registerSchemaResolver(
            $class::getFormId(),
            static fn (): Schema => $class::form(new Schema)->model($class::getModel()),
        );

        $this->resourceClasses[$tableId] = $class;

        // Register every relation manager the resource lists under its table
        // id, keyed by the to-many relationship each hosts (slice 1.8) — the
        // scoped relation endpoint resolves them by name.
        foreach ($class::getRelations() as $relationClass) {
            $this->relationManagers[$tableId][$relationClass::getRelationshipName()] = $relationClass;
        }

        return $this;
    }

    /**
     * The table ids of the discovered resources — the package auto-registers
     * the list and create page routes from them (docs/ARCHITECTURE.md,
     * "Resources").
     *
     * @return array<int, string>
     */
    public function getResourceTableIds(): array
    {
        return array_keys($this->resourceClasses);
    }

    /**
     * Every discovered resource class, in discovery order.
     *
     * @return array<int, class-string<resource>>
     */
    public function getResources(): array
    {
        return array_values($this->resourceClasses);
    }

    /**
     * Register the consumer's panel factory (mirroring Filament's
     * `Filament::registerPanel(fn ...)`). Called from a `PanelProvider`'s
     * `register()`. Only one panel is supported — registering a second throws
     * so a consumer mistake fails loudly instead of silently winning the last
     * factory. Registering invalidates any panel built before the factory was
     * known (the package may have built one during its own boot, before this
     * provider registered) — the factory owns the panel from then on, so a
     * provider that also mutates `panel()` directly should do so in its own
     * `boot()`, after this registration.
     */
    public function registerPanel(Closure $factory): static
    {
        if ($this->panelFactory !== null) {
            throw new LogicException('Only one panel may be registered — Refilament currently supports a single panel provider.');
        }

        $this->panelFactory = $factory;
        $this->panel = null;

        return $this;
    }

    /**
     * The panel config served to the frontend shell (slice 1.9). Built on
     * first access from the currently-discovered resources, lazily so it picks
     * up every resource registered during the request's bootstrap. When a
     * consumer panel provider registered, its `panel()` override runs on top
     * of the config-seeded panel; otherwise the config-driven panel is the
     * whole story (workbench / default mode).
     */
    public function panel(): Panel
    {
        return $this->panel ??= $this->buildPanel();
    }

    protected function buildPanel(): Panel
    {
        $panel = $this->configPanel();

        if ($this->panelFactory !== null) {
            $panel = ($this->panelFactory)($panel);
        }

        return $panel;
    }

    /**
     * The config-seeded panel — the defaults from config/refilament.php plus
     * the discovered resources and pages. The consumer's `panel()` override
     * receives exactly this, so it only chains what it wants to change
     * (identity, path, colors, middleware, widgets, render hooks).
     */
    protected function configPanel(): Panel
    {
        return Panel::make()
            ->resources($this->getResources())
            ->pages((array) config('refilament.panel.pages', []))
            // The discovery calls fall back to the package defaults so a
            // consumer's stale published config (one published before a
            // feature's keys existed) never silently disables pages or
            // cluster discovery — the config merge replaces the whole
            // `panel` array, so missing keys would otherwise read as null
            // and discover nothing.
            ->discoverPages(
                (string) config('refilament.panel.pages_path', app_path('Refilament/Pages')),
                (string) config('refilament.panel.pages_namespace', 'App\\Refilament\\Pages'),
            )
            ->clusters((array) config('refilament.panel.clusters', []))
            ->discoverClusters(
                (string) config('refilament.panel.clusters_path', app_path('Refilament/Clusters')),
                (string) config('refilament.panel.clusters_namespace', 'App\\Refilament\\Clusters'),
            )
            ->id(config('refilament.panel.id', 'refilament'))
            ->path((string) config('refilament.panel.path', 'refilament'))
            ->brandName(config('refilament.panel.brand_name', 'Refilament'))
            ->brandLogo(config('refilament.panel.brand_logo'))
            ->topNavigation(config('refilament.panel.top_navigation', false))
            ->dashboardUrl(config('refilament.panel.dashboard_url'))
            ->colors(config('refilament.panel.colors', []))
            ->widgets(config('refilament.panel.widgets', []))
            ->middleware(config('refilament.panel.middleware', []))
            ->authGuard(config('refilament.panel.auth_guard', 'web'))
            ->loginUrl(config('refilament.panel.login_url'))
            ->authMiddleware(config('refilament.panel.auth_middleware', []))
            ->login(config('refilament.panel.login_page'))
            ->registration(config('refilament.panel.registration_page'))
            ->passwordReset(
                config('refilament.panel.request_password_reset_page'),
                config('refilament.panel.reset_password_page'),
            )
            ->emailVerification(config('refilament.panel.email_verification_page'))
            ->twoFactorAuthentication(config('refilament.panel.two_factor_authentication', false))
            ->profile(config('refilament.panel.profile_page'));
    }

    /**
     * Register every package route under the panel's URL prefix — the
     * dashboard, the typed endpoints and the page routes all live at
     * /{panel path}/... A consumer's `->path('admin')` therefore moves the
     * whole panel in one place. Called from the service provider's `booted()`
     * hook so the panel is resolved after every provider (including a
     * consumer's PanelProvider) has registered.
     */
    public function registerRoutes(): static
    {
        // The whole route group mounts inside the framework's `web` middleware
        // group (mirroring Filament's `->hasRoutes('web')`), so every panel
        // route gets sessions + CSRF + SubstituteBindings for free — the
        // shell's CSRF-bearing POSTs validate against a real session, and the
        // panel's own `->middleware()` list in the routes file still appends
        // after the group.
        RouteFacade::middleware(['web'])
            ->prefix($this->panel()->getPath())
            ->group(static function (): void {
                require __DIR__.'/../routes/refilament.php';
            });

        return $this;
    }

    /**
     * Register the panel's first-party auth routes (docs/ROADMAP.md "1.9 auth
     * pages") under the panel's URL prefix, plus the Fortify view responses
     * that render the panel's pages. Called from the service provider's
     * `booted()` hook (after every provider — including a consumer's
     * PanelProvider — has registered and booted, so the panel config is
     * final). Fortify's own routes are left alone; this panel owns the
     * routes it registers, delegating to Fortify's controllers and their
     * machinery (login pipeline, rate limiting, password broker, email
     * verification, two-factor challenge).
     *
     * No-op unless at least one auth page is enabled — the default panel
     * ships with none (the permissive workbench stays untouched, and an
     * installed-but-unused Fortify keeps out of the way).
     */
    public function registerAuthRoutes(): static
    {
        $panel = $this->panel();

        // Shared auth mode (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md): the
        // panel owns no auth surface — no routes, and Fortify's global config
        // (guard/home/features) is left entirely to the app. The auth-page
        // setters throw in shared mode, so hasAuthPages() is always false
        // here; the explicit check keeps the intent visible and guarantees
        // the config below never runs.
        if ($panel->getAuthMode()->isShared() || ! $panel->hasAuthPages()) {
            return $this;
        }

        // Fortify's controllers read these at request time (features are
        // consulted inside the login pipeline / controllers, not at route
        // registration), so setting them here — after Fortify's own boot — is
        // the right moment. `fortify.home` is where Fortify's responses
        // redirect after login/register: the panel dashboard. The guard is the
        // panel's own, so the panel's auth pages authenticate the same guard
        // the auth gate checks.
        config([
            'fortify.guard' => $panel->getAuthGuard(),
            'fortify.home' => $panel->getDashboardUrl(),
            'fortify.features' => $this->fortifyFeatures($panel),
        ]);

        $this->registerAuthActions($panel);

        $this->registerAuthViews($panel);

        $this->registerAuthResponses($panel);

        $this->registerPasswordResetUrl($panel);

        RouteFacade::middleware(['web'])
            ->prefix($panel->getPath())
            ->group(static function (): void {
                require __DIR__.'/../routes/auth.php';
            });

        return $this;
    }

    /**
     * Point the password-reset email's link at the panel's own reset page
     * (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md). Laravel's default
     * `ResetPassword` notification builds its URL from the *global*
     * `password.reset` route name, which the panel no longer registers — the
     * email would link to the app's reset page (or throw when no such route
     * exists). `createUrlUsing()` is the framework's documented hook for this;
     * the static is public, so a consumer's own callback (registered before
     * this booted hook) always wins.
     */
    protected function registerPasswordResetUrl(Panel $panel): void
    {
        if (! $panel->hasPasswordReset() || ResetPassword::$createUrlCallback !== null) {
            return;
        }

        ResetPassword::createUrlUsing(
            fn (CanResetPassword $notifiable, string $token): string => url(route("refilament.{$panel->getId()}.auth.password.reset", [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)),
        );
    }

    /**
     * The Fortify feature flags the panel's auth pages imply — the union of
     * the standard authenticated-account features (profile + password
     * updates, whose endpoints the panel registers for consumer settings
     * pages) and the panel-enabled flows (registration, password reset,
     * email verification, two-factor). Passkeys are included only when the
     * separate `laravel/passkeys` package is installed.
     *
     * @return array<int, string>
     */
    protected function fortifyFeatures(Panel $panel): array
    {
        $features = [
            Features::updateProfileInformation(),
            Features::updatePasswords(),
        ];

        if ($panel->hasRegistration()) {
            $features[] = Features::registration();
        }

        if ($panel->hasPasswordReset()) {
            $features[] = Features::resetPasswords();
        }

        if ($panel->hasEmailVerification()) {
            $features[] = Features::emailVerification();
        }

        if ($panel->hasTwoFactorAuthentication()) {
            $features[] = Features::twoFactorAuthentication([
                'confirm' => true,
                'confirmPassword' => true,
            ]);
        }

        if (class_exists(PasskeysServiceProvider::class)) {
            $features[] = Features::passkeys([
                'confirmPassword' => true,
            ]);
        }

        return $features;
    }

    /**
     * Bind the first-party default actions for the enabled flows, unless the
     * consumer already bound their own (`Fortify::createUsersUsing()` /
     * `resetUserPasswordsUsing()`) — registration and password reset then
     * work out of the box, and a consumer's binding always wins.
     */
    protected function registerAuthActions(Panel $panel): void
    {
        if ($panel->hasRegistration() && ! app()->bound(CreatesNewUsers::class)) {
            Fortify::createUsersUsing(CreateNewUser::class);
        }

        if ($panel->hasPasswordReset() && ! app()->bound(ResetsUserPasswords::class)) {
            Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        }

        // Profile and password update actions are always bound when the panel
        // has auth pages enabled (the endpoints are unconditional). The
        // package's own defaults handle the standard name/email/password
        // fields through the connection's users table; a consumer's
        // `Fortify::updateUserProfileInformationUsing(...)` /
        // `updateUserPasswordUsing(...)` always wins.
        if (! app()->bound(UpdatesUserProfileInformation::class)) {
            Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        }

        if (! app()->bound(UpdatesUserPasswords::class)) {
            Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        }
    }

    /**
     * Point Fortify's view-response contracts at the panel's Inertia pages —
     * each enabled page's component, with its per-request props, rendered
     * through the package root view so the auth pages boot the panel's own
     * bundle. A consumer overriding a page class only has to register a React
     * component under that class's component name.
     */
    protected function registerAuthViews(Panel $panel): void
    {
        $inertia = static function (string $component, array $data = []): Response {
            return Inertia::render($component, $data)->rootView('refilament::app');
        };

        if ($panel->hasLogin()) {
            Fortify::loginView(
                fn (Request $request) => $inertia($panel->getLoginPage()::getComponent(), $panel->getLoginPage()::getViewData($request)),
            );
        }

        if ($panel->hasRegistration()) {
            Fortify::registerView(
                fn (Request $request) => $inertia($panel->getRegistrationPage()::getComponent(), $panel->getRegistrationPage()::getViewData($request)),
            );
        }

        if ($panel->hasPasswordReset()) {
            Fortify::requestPasswordResetLinkView(
                fn (Request $request) => $inertia($panel->getRequestPasswordResetPage()::getComponent(), $panel->getRequestPasswordResetPage()::getViewData($request)),
            );

            Fortify::resetPasswordView(
                fn (Request $request) => $inertia($panel->getResetPasswordPage()::getComponent(), $panel->getResetPasswordPage()::getViewData($request)),
            );
        }

        if ($panel->hasEmailVerification()) {
            Fortify::verifyEmailView(
                fn (Request $request) => $inertia($panel->getEmailVerificationPage()::getComponent(), $panel->getEmailVerificationPage()::getViewData($request)),
            );
        }

        if ($panel->hasTwoFactorAuthentication()) {
            Fortify::twoFactorChallengeView(
                fn (Request $request) => $inertia($panel->getTwoFactorChallengePage()::getComponent(), $panel->getTwoFactorChallengePage()::getViewData($request)),
            );
        }

        // Password-confirmation view bound unconditionally (the route is always
        // mounted) — casts ConfirmablePasswordController::show() to Inertia so
        // the password.confirm middleware never hits a ViewNotFound error.
        Fortify::confirmPasswordView(
            fn (Request $request) => $inertia(ConfirmPassword::getComponent(), ConfirmPassword::getViewData($request)),
        );
    }

    /**
     * Point Fortify's responses at the panel — the panel-scoped route names
     * (docs/AUTH-ROUTE-COLLISION-INVESTIGATION.md). Several of Fortify's
     * responses hardcode a global route name (`login`, `two-factor.login`)
     * that no longer exists under the panel's scoped names; each is rebound
     * here to a first-party response that resolves the panel's own route
     * instead. Fortify's auth machinery (hashing, 2FA verification, rate
     * limiting) is untouched — only the redirect targets change.
     *
     * Fortify's own provider already bound each contract at bootstrap, so
     * `app()->bound()` can't tell the package's binding from a consumer's —
     * instead we resolve the current instance and only replace it when it is
     * still Fortify's default response. A consumer's own binding therefore
     * always wins.
     */
    protected function registerAuthResponses(Panel $panel): void
    {
        $current = app(LogoutResponse::class);

        if (get_class($current) === \Laravel\Fortify\Http\Responses\LogoutResponse::class) {
            app()->instance(
                LogoutResponse::class,
                new Auth\Responses\LogoutResponse($panel),
            );
        }

        // Two-factor: the login→challenge bounce, the failed-challenge
        // response and (in routes/auth.php) the challenge page controller all
        // hardcode Fortify's global names. Rebind the ones resolved through
        // the container so they land on the panel's own challenge page.
        if ($panel->hasTwoFactorAuthentication()) {
            // Both contracts resolve cleanly (their default concretes have no
            // required primitives), so "still the default" is answered by
            // resolving the current instance and comparing classes.
            $challenge = app(RedirectsIfTwoFactorAuthenticatable::class);

            if (get_class($challenge) === RedirectIfTwoFactorAuthenticatable::class) {
                app()->scoped(
                    RedirectsIfTwoFactorAuthenticatable::class,
                    fn (): RedirectIfTwoFactorAuthenticatable => app(Auth\Actions\RedirectIfTwoFactorAuthenticatable::class),
                );
            }

            $failedTwoFactor = app(FailedTwoFactorLoginResponse::class);

            if (get_class($failedTwoFactor) === \Laravel\Fortify\Http\Responses\FailedTwoFactorLoginResponse::class) {
                app()->instance(
                    FailedTwoFactorLoginResponse::class,
                    new Auth\Responses\FailedTwoFactorLoginResponse($panel),
                );
            }
        }

        // Password reset: Fortify's default success response evaluates
        // `route('login')` eagerly as its fallback target, which throws once
        // the global name is gone. Rebind with a factory — the reset status is
        // per-request (the controller resolves the contract with ['status' =>
        // $status]), so the binding must forward the container parameters.
        // The default concrete can't be resolved without that per-request
        // status, so the "still default" check inspects the binding rather
        // than the instance.
        if ($panel->hasPasswordReset()) {
            if ($this->isBoundToDefault(
                PasswordResetResponse::class,
                \Laravel\Fortify\Http\Responses\PasswordResetResponse::class,
            )) {
                app()->bind(
                    PasswordResetResponse::class,
                    fn (Container $app, array $parameters): PasswordResetResponse => new Auth\Responses\PasswordResetResponse($parameters['status'] ?? ''),
                );
            }
        }
    }

    /**
     * Whether a container contract is still bound to Fortify's default
     * concrete — the package's rebindings only apply when the consumer hasn't
     * bound their own implementation. Inspects the binding (not the resolved
     * instance): some defaults (e.g. PasswordResetResponse) can't be resolved
     * without per-request parameters, and a consumer's `instance()` binding
     * never appears in the bindings table at all, which safely reads as
     * "not the default" — the consumer wins.
     */
    protected function isBoundToDefault(string $abstract, string $defaultClass): bool
    {
        return (app()->getBindings()[$abstract]['concrete'] ?? null) === $defaultClass;
    }

    /**
     * The resource class registered under a table id, if any.
     *
     * @return class-string<resource>|null
     */
    public function getResourceClass(string $tableId): ?string
    {
        return $this->resourceClasses[$tableId] ?? null;
    }

    /**
     * The current panel user authorization decisions are made for (slice 4.1
     * — docs/ROADMAP.md "4.1 Authorization"). Resolved lazily per request
     * through the panel's auth guard, so it always reflects the actual
     * visitor (there is no persistent component between requests to remember
     * state). Resource, Action and BulkAction all delegate here so an
     * ability check for a table action uses the same user as a resource page
     * gate.
     */
    public function authorizationUser(): ?Authenticatable
    {
        $guard = $this->panel()->getAuthGuard();

        $user = app('auth')->guard($guard)->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * The relation manager class registered under a resource's table id and a
     * relationship name, if any (slice 1.8 — docs/CONTRACT.md, "Relations").
     *
     * @return class-string<RelationManager>|null
     */
    public function getRelationManager(string $resourceTableId, string $relationshipName): ?string
    {
        return $this->relationManagers[$resourceTableId][$relationshipName] ?? null;
    }

    /**
     * Every relation manager registered under a resource's table id, keyed by
     * the relationship each hosts (slice 1.8). Used by record pages to know
     * which manager-driven tabs to render under an owner's edit/view form.
     *
     * @return array<string, class-string<RelationManager>>
     */
    public function getRelationManagers(string $resourceTableId): array
    {
        return $this->relationManagers[$resourceTableId] ?? [];
    }

    /**
     * Auto-register one page route per page name in every discovered
     * resource's getPages() map (slice 1.6 — docs/ROADMAP.md "1.6 Page
     * system"). Called from the service provider at boot; also re-runnable
     * so late-registered resources get their page routes.
     *
     * Page names are registered once — every resource shares the built-in
     * index/create/edit/view URI shapes (and Laravel's RouteCollection is
     * keyed by method|uri, so a second registration would silently replace
     * the first), so the first resource to declare a page name wins its
     * path, like Filament's panel-wide page-name uniqueness. A resource
     * declaring the same name under a *different* path is a configuration
     * bug (the shared route is what renders — the second path could never
     * match), so it throws instead of silently shadowing.
     */
    public function registerPageRoutes(): static
    {
        $registeredPageNames = [];
        $registeredPagePaths = [];

        foreach ($this->getResourceTableIds() as $resourceId) {
            $class = $this->getResourceClass($resourceId);

            if ($class === null) {
                continue;
            }

            foreach ($class::getPages() as $pageName => $registration) {
                $path = $registration->getPath();

                // A page that hosts a form (the page-forms slice — any page
                // declaring `form()`) registers its schema resolver here, so
                // the typed submit / validate endpoints can rebuild it per
                // request. Runs before the duplicate-name guard: every page
                // class registers its own resolver even when the shared page
                // route was already claimed by another resource.
                $pageClass = $registration->getPage();

                // hasFormSchema() (not getFormSchema()) gates the resolver
                // registration: it builds the schema without the
                // singular-resource auto-wire, so boot stays free of DB
                // queries even for a singular page on a fresh install.
                if ($pageClass::hasFormSchema()) {
                    $this->registerSchemaResolver(
                        $pageClass::getFormId(),
                        static fn (): ?Schema => $pageClass::getFormSchema(),
                    );
                }

                // A page hosting a table (the pages-as-tables slice)
                // registers its table resolver the same way — the typed
                // table endpoints (index / actions / bulk) rebuild the
                // table per request, so pagination, sorting, search and
                // filter closures re-run with fresh state.
                if ($pageClass::getTable() !== null) {
                    $this->registerTable(
                        $pageClass::getTableId(),
                        static fn (): Table => $pageClass::getTable(),
                    );
                }

                if (isset($registeredPageNames[$pageName])) {
                    // Same page name across resources must mean the same path
                    // — a resource declaring a different path under a name
                    // another resource already claimed would be silently
                    // shadowed (the shared route is what renders), so it is a
                    // configuration bug, not a supported override.
                    if ($path !== null && $path !== $registeredPagePaths[$pageName]) {
                        throw new LogicException(
                            "Page [{$pageName}] is registered with conflicting paths "
                            ."[{$registeredPagePaths[$pageName]}] and [{$path}] — resources must agree "
                            .'on the path of a shared page name.',
                        );
                    }

                    continue;
                }

                $registeredPageNames[$pageName] = true;
                $registeredPagePaths[$pageName] = $path;

                $registration->registerRoute("refilament.resource.{$pageName}");
            }
        }

        $pages = $this->panel()->getPages();

        if ($pages !== []) {
            $pageSlugs = [];
            $clusterPageSlugs = [];

            foreach ($pages as $pageClass) {
                // Standalone page forms (the page-forms slice): register the
                // page's schema resolver so its typed submit / validate
                // endpoints work, exactly like resource-page forms above.
                // hasFormSchema() keeps boot DB-free (see the resource-page
                // registration above).
                if ($pageClass::hasFormSchema()) {
                    $this->registerSchemaResolver(
                        $pageClass::getFormId(),
                        static fn (): ?Schema => $pageClass::getFormSchema(),
                    );
                }

                // Standalone page tables (the pages-as-tables slice): the
                // typed table endpoints resolve through the page's resolver,
                // exactly like resource-page tables above.
                if ($pageClass::getTable() !== null) {
                    $this->registerTable(
                        $pageClass::getTableId(),
                        static fn (): Table => $pageClass::getTable(),
                    );
                }

                // The slug uniqueness gate applies within each routing
                // family: clustered pages live under their cluster's segment,
                // so they can never collide with a flat page or with each
                // other across clusters.
                if ($pageClass::isClustered()) {
                    $clusterSlug = $pageClass::getCluster()::getSlug();
                    // The {page} gate uses the page's own (possibly
                    // overridden) bare slug — the controller re-combines it
                    // with the cluster segment for resolvePanelPage().
                    $clusterPageSlugs[$clusterSlug][$pageClass::getSlug()] = $pageClass;

                    continue;
                }

                $slug = $pageClass::getSlug();

                if (isset($pageSlugs[$slug])) {
                    throw new LogicException(
                        "Standalone pages [{$pageSlugs[$slug]}] and [{$pageClass}] both use the "
                        ."slug [{$slug}] — panel pages must have unique slugs.",
                    );
                }

                $pageSlugs[$slug] = $pageClass;
            }

            // The shared middleware list for the page + cluster routes —
            // combined into a SINGLE ->middleware() call: on Laravel 13 the
            // RouteRegistrar's attribute() REPLACES rather than merges, so
            // chaining a second ->middleware() would drop the `web` group
            // (and with it StartSession) — the standalone pages would then
            // run without a session and the auth gate would reject every
            // request. This single call yields exactly the middleware the
            // dashboard and typed endpoints carry (routes/refilament.php
            // applies `web` as the one group wrapper and the panel list
            // per-route).
            $pageMiddleware = ['web', ...$this->panel()->getMiddleware(), PanelAuthenticate::class, AppendInertiaVersion::class];

            // One shared route serves every flat standalone panel page — the
            // where() gate restricts it to the declared slugs, mirroring the
            // shared {resource} route for resource pages. Registered under
            // the panel's path (like every other panel route) and after the
            // resource routes above, so it can't shadow the exact-path
            // dashboard or collide with a discovered resource id.
            if ($pageSlugs !== []) {
                RouteFacade::middleware($pageMiddleware)
                    ->prefix($this->panel()->getPath())
                    ->group(static function () use ($pageSlugs): void {
                        RouteFacade::get('{page}', [PanelPageController::class, 'show'])
                            ->where('page', implode('|', array_map('preg_quote', array_keys($pageSlugs))))
                            ->name('refilament.page');
                    });
            }

            // Clustered standalone pages (the page-clusters slice) serve at
            // /{cluster}/{page}: one shared route per cluster, gated to the
            // cluster's slug and the basename slugs of the pages inside it.
            foreach ($clusterPageSlugs as $clusterSlug => $members) {
                RouteFacade::middleware($pageMiddleware)
                    ->prefix($this->panel()->getPath())
                    ->group(static function () use ($clusterSlug, $members): void {
                        RouteFacade::get('{cluster}/{page}', [PanelPageController::class, 'show'])
                            ->where('cluster', preg_quote($clusterSlug, '#'))
                            ->where('page', implode('|', array_map('preg_quote', array_keys($members))))
                            ->name('refilament.cluster.page');
                    });
            }

            // Every cluster registers a redirect route at its own slug — a
            // cluster never renders; the URL redirects to the first
            // accessible clustered component (mirroring Filament's
            // Cluster::mount()). The shared {cluster} URI is gated to the
            // cluster's own slug, so each cluster owns its segment.
            foreach ($this->getClusters() as $clusterClass) {
                RouteFacade::middleware($pageMiddleware)
                    ->prefix($this->panel()->getPath())
                    ->group(static function () use ($clusterClass): void {
                        RouteFacade::get('{cluster}', [ClusterRedirectController::class, '__invoke'])
                            ->where('cluster', preg_quote($clusterClass::getSlug(), '#'))
                            ->name('refilament.cluster');
                    });
            }
        }

        return $this;
    }

    /**
     * The standalone panel page whose slug matches a given
     * `{page}` route segment, or null if none. Used by PanelPageController
     * to resolve the page class from the URL — the panel auto-registers a
     * single shared route gated to the slugs of every standalone page, so
     * the lookup is the inverse of that gate.
     *
     * @return class-string<Page>|null
     */
    public function resolvePanelPage(string $slug): ?string
    {
        foreach ($this->panel()->getPages() as $pageClass) {
            // The full path (cluster-prefixed for clustered pages, the
            // page-clusters slice).
            if ($pageClass::getSlugPath() === $slug) {
                return $pageClass;
            }
        }

        return null;
    }
}
