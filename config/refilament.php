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
        'brand_name' => 'Refilament',
        'dashboard_url' => '/refilament',
        'colors' => [
            'primary' => '#6366f1',
            'primary_foreground' => '#ffffff',
        ],
        'widgets' => [],

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
    ],

];
