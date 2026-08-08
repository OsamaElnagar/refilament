<div align="center">
    <h1>Refilament</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://img.shields.io/packagist/v/osamaelnagar/refilament.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://img.shields.io/packagist/php-v/osamaelnagar/refilament.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://badge.laravel.cloud/badge/osamaelnagar/refilament?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/osamaelnagar/refilament/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/osamaelnagar/refilament/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://img.shields.io/packagist/dt/osamaelnagar/refilament.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require osamaelnagar/refilament
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="refilament"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="refilament-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="refilament-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="refilament-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="refilament-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="refilament-assets"
```

## Usage

Refilament is a Laravel + Inertia + React admin panel — the Filament experience rebuilt on
the official React starter kit's stack. Install it in any Laravel 12/13 app and get a panel at
`/refilament` with CRUD pages, tables, forms, charts, global search and notifications, served from
a prebuilt bundle (no npm setup in the consumer).

### 1. Install

```bash
composer require osamaelnagar/refilament

php artisan refilament:install
```

The install command publishes the config, the prebuilt React bundle and the migrations, generates
a consumer-owned **panel provider** (`app/Providers/RefilamentPanelProvider.php`) and registers it
in `bootstrap/providers.php` — the Filament flow. Then migrate and open `/refilament`:

```bash
php artisan migrate
```

The panel provider is where you own the panel — it receives the config-seeded panel and chains
your overrides, Filament-style:

```php
class RefilamentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')                             // the panel's URL prefix
            ->brandName('My App')
            ->colors(['primary' => '#e11d48'])
            // ->middleware(['web'])                    // sessions + CSRF on the panel's routes
            // ->authMiddleware([Authenticate::class])  // turn the access gate on
            // ->loginUrl('/login')
            // ->widgets([StatsOverview::class])
            // ->renderHook('sidebar-footer', 'my-component')
            // ->databaseNotifications()
            ;
    }
}
```

Every panel route — the dashboard, resource pages, standalone pages and the typed endpoints —
lives under the panel's `path`, and the shell builds its own URLs (search, notifications, table
and relation endpoints) from the same path, so `->path('admin')` moves the whole panel to
`/admin`. The prebuilt bundle loads through the package's own root view, so the consumer app's
Vite bundle is never touched.

### 2. Create your first resource

```bash
php artisan refilament:make-resource Post --model=App\Models\Post --generate
```

This scaffolds `app/Refilament/Resources/Post/` with a `PostResource`, a `Schemas/PostForm`
(columns introspected from the `posts` table) and a `Tables/PostsTable`. Visit `/refilament` —
your resource appears in the sidebar with list / create / edit / view pages.

You can also build the resource by hand — the resource is a thin class composing a form and a
table, mirroring Filament:

```php
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            TextInput::make('slug')->required(),
            Select::make('status')->options(PostStatus::class),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Post::query())
            ->columns([
                Column::make('title')->searchable()->sortable(),
                Column::make('status')->badge(),
                Column::make('published_at')->date(),
            ])
            ->headerActions([Action::make('create')->type('create')->schema('post-form')])
            ->recordActions([
                Action::make('edit')->type('edit')->schema('post-form'),
                Action::make('delete')->color('danger')->requiresConfirmation(),
            ]);
    }
}
```

### 3. What you get

- **Tables** — server-side pagination, sorting, search, filters, grouping, footer summaries,
  toggleable columns, bulk actions + soft deletes, view state persisted in the URL.
- **Forms** — text/number/date/time inputs, select (with dependent options), textarea, checkbox,
  toggle, radio; validation rules stay server-authoritative; computed fields for live client-side
  arithmetic.
- **Pages** — Filament's `getPages()` model: list / create / edit / view plus custom resource pages
  and standalone pages.
- **Panel shell** — sidebar navigation (groups, collapse persistence), brand + theme colors,
  dark mode, global search (Cmd/Ctrl+K), database-notifications bell.
- **Panel provider** — a consumer-owned `PanelProvider` (`panel(Panel $panel): Panel`) registered
  by `refilament:install`: path, brand, colors, middleware, auth gate, widgets and render hooks.
- **Widgets** — stats overview, bar/line/pie/doughnut charts (with optional polling + filters),
  table widgets.
- **Infolists** — read-only record display on view pages.
- **Authorization** — Laravel policy-backed `can*()` gates on resources, pages and actions
  (permissive by default).
- **i18n** — opt-in `translateLabel()` on every label seat, server-side.

Everything is one vertical slice at a time and the full JSON contract is documented in
[`docs/CONTRACT.md`](docs/CONTRACT.md). The roadmap lives in [`docs/ROADMAP.md`](docs/ROADMAP.md).

### Development

The package ships a testbench workbench (`workbench/`) used to develop and demo every slice:

```bash
composer install
npm install
npm run build:assets   # the prebuilt consumer bundle → public/refilament.{js,css}
npm run dev            # workbench Vite dev server (hot reload)
composer serve         # workbench app at http://127.0.0.1:8000
```

Run the test suite with `composer test` (PHPStan + Pint + Pest, 573 tests green).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Beta status

The current engine is feature-complete against the MVP roadmap and ships as `v0.1.1-beta.1`:
installable, with a prebuilt bundle, generator commands and a documented contract. The remaining
work before a stable v1 is consumer hardening — see `docs/ROADMAP.md` Phase 5 (plugin contract, CI
matrix, `vendor:publish` consumer smoke test) — plus the deferred items listed in the roadmap.

**Known beta limitations (deliberate, tracked in the roadmap):**

- **Panel routes are not in the `web` middleware group by default.** A fresh panel has no session,
  no CSRF enforcement, and no Inertia version header — the panel works, but in-app Inertia
  navigation falls back to full page loads and the notifications bell needs a session to resolve
  the user. This is an explicit opt-in: add `->middleware(['web'])` to the panel provider to get
  sessions + CSRF on every panel route (the default stays bare so a fresh install works with zero
  configuration).

## Contributing

Thank you for considering contributing to Refilament! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Osama Mohammed Elnagar](https://github.com/osamaelnagar)
- [All Contributors](../../contributors)

## License

Refilament is open-sourced software licensed under the [MIT license](LICENSE.md).
