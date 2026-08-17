<?php

declare(strict_types=1);

return [

    'placeholder' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Resource discovery
    |--------------------------------------------------------------------------
    |
    | Every *Resource.php class in `resources.path` is discovered at boot and
    | its table and form are registered under their ids (see Resource).
    |
    */

    'resources' => [
        'path' => app_path('Refilament/Resources'),
        'namespace' => 'App\\Refilament\\Resources',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel shell (slice 1.9)
    |--------------------------------------------------------------------------
    |
    | `id` names the panel, `brand_name` is the brand shown in the sidebar
    | header, and `dashboard_url` is where the brand links (the dashboard
    | route serving `widgets`). `colors` becomes CSS custom properties the
    | shell applies to theme the brand (key = the `--{key}` suffix, value = a
    | CSS color). `widgets` are the widget classes the dashboard route renders
    | on each request. The sidebar navigation itself derives from the
    | discovered resources' navigation surface (see Panel).
    |
    */

    'panel' => [
        'id' => 'refilament',
        /*
        | The URL prefix every panel route serves under — '/{path}' — and the
        | prefix the shell's own URLs (search, notifications, table and
        | relation endpoints, page links) are built from. A consumer's
        | RefilamentPanelProvider overrides this with ->path('admin').
        */
        'path' => 'refilament',
        'brand_name' => 'Refilament',
        /*
        | A brand logo URL rendered beside the brand name in the shell (or a
        | closure returning one). Null renders the default mark.
        */
        'brand_logo' => null,
        /*
        | Render the navigation in a top bar instead of the sidebar
        | (mirrors Filament's topNavigation()).
        */
        'top_navigation' => false,
        /*
        | The brand link's target (the dashboard). Null derives from `path`,
        | so changing the panel's URL prefix moves the brand link with it.
        */
        'dashboard_url' => null,
        'colors' => [
            'primary' => '#6366f1',
            'primary_foreground' => '#ffffff',
        ],
        'widgets' => [],

        /*
        | Middleware applied to every panel route (the shell pages and the
        | typed endpoints). Empty by default — the panel routes register bare.
        | Add the framework's `web` group here to opt the panel into sessions
        | + CSRF, or any of your own middleware. The access gate itself is a
        | separate opt-in (`auth_middleware` below).
        */
        'middleware' => [],

        /*
        |--------------------------------------------------------------------------
        | Standalone pages (slice 1.9 "->pages([...])")
        |--------------------------------------------------------------------------
        |
        | `pages` is an explicit list of standalone panel page classes (pages
        | not tied to a resource, extending the Panel\Pages base) to register.
        | `pages_path`/`pages_namespace` auto-discovery registers every
        | non-abstract page class in the folder, mirroring Filament's
        | `discoverPages()`. Each page is served through the shared
        | PanelPageController route at /refilament/{slug}; pages that opt in
        | via shouldRegisterNavigation() also appear in the sidebar.
        |
        */

        'pages' => [],
        'pages_path' => app_path('Refilament/Pages'),
        'pages_namespace' => 'App\\Refilament\\Pages',

        /*
        |--------------------------------------------------------------------------
        | Page clusters (the page-clusters slice)
        |--------------------------------------------------------------------------
        |
        | `clusters` is an explicit list of cluster classes. `clusters_path` /
        | `clusters_namespace` auto-discovery registers every cluster class in
        | the folder, mirroring Filament's `discoverClusters()`. A cluster
        | groups pages and resources (those declaring `$cluster`) under one
        | sidebar entry; its own URL redirects to the first accessible member.
        |
        */

        'clusters' => [],
        'clusters_path' => app_path('Refilament/Clusters'),
        'clusters_namespace' => 'App\\Refilament\\Clusters',

        /*
        |--------------------------------------------------------------------------
        | Auth gate (slice 1.9 "auth gate")
        |--------------------------------------------------------------------------
        |
        | `auth_middleware` is applied to the panel's shell-rendering routes
        | (the dashboard and every resource page). Leave it empty to keep the
        | panel permissive (every shell page serves openly — the default, and
        | the workbench mode). Register `Authenticate::class` here to turn the
        | gate on: visitors must then authenticate against `auth_guard` before
        | any shell page renders, and unauthenticated requests are redirected
        | to `login_url`. The JSON API endpoints (table/schema/action) are not
        | gated — they are reachable only from within a rendered shell page.
        |
        */

        'auth_guard' => 'web',
        'login_url' => null,
        'auth_middleware' => [],

        /*
        |--------------------------------------------------------------------------
        | First-party auth pages (docs/ROADMAP.md "1.9 auth pages")
        |--------------------------------------------------------------------------
        |
        | The panel's own auth pages, backed by Fortify and served under the
        | panel's URL prefix (/{path}/login, /{path}/register, ...). Each key
        | names the page class to serve (see Auth\Pages), or null to leave the
        | page disabled — the default, matching Filament, where the panel ships
        | with no auth pages until `->login()` (etc.) is called. The panel
        | provider overrides these with `->login()`, `->registration()`,
        | `->passwordReset()` and `->emailVerification()`.
        |
        */

        'login_page' => null,
        'registration_page' => null,
        'request_password_reset_page' => null,
        'reset_password_page' => null,
        'email_verification_page' => null,
        'two_factor_authentication' => false,

        /*
        |--------------------------------------------------------------------------
        | Profile page (Filament's ->profile())
        |--------------------------------------------------------------------------
        |
        | The panel's EditProfile page at /{path}/user/profile, where the
        | authenticated user updates their name/email/password and manages
        | two-factor authentication. Null disables the page (the default);
        | set to `EditProfile::class` to enable, or a consumer's own page
        | class extending Auth\Pages\AuthPage. The panel provider overrides
        | this with `->profile()`.
        |
        */

        'profile_page' => null,
    ],

];
