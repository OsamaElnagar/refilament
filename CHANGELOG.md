# Release Notes

## [Unreleased](https://github.com/osamaelnagar/refilament/compare/v0.1.1-beta.1...1.x)

### Added

- **Panel routes in the `web` middleware group** — every panel route now mounts inside the
  framework's `web` group (mirroring Filament's `->hasRoutes('web')`), so sessions + CSRF +
  SubstituteBindings apply to the whole panel. The shell's POSTs validate against the real
  session token, and the POST test suite was reworked to exercise that pipeline (session token
  injected per request, plus an enforcement test with the env=testing CSRF bypass disabled).
- **Filament-style install experience** — `refilament:install` publishes config / assets /
  migrations, generates a consumer-owned `app/Providers/RefilamentPanelProvider.php` (mirroring
  Filament's `PanelProvider` + `panel(Panel $panel): Panel` contract) and registers it in
  `bootstrap/providers.php`.
- **`Panel::path()`** — the panel's URL prefix. `->path('admin')` moves every panel route and the
  shell's own URLs (search, notifications, table / relation endpoints, page links) to `/admin`;
  the dashboard URL derives from the path unless explicitly set.
- **`Panel::middleware()`** — middleware applied to every panel route (opt into `web` for sessions
  + CSRF); the auth gate (`->authMiddleware()`) is now read from the live panel per request, so
  arming it needs no route re-registration.
- Panel resolution through the consumer's provider: `Refilament::registerPanel()` runs the
  provider's `panel()` on top of the config-seeded panel; without a provider the config-driven
  panel is the whole story (workbench / default mode).

## [v0.1.1-beta.1](https://github.com/osamaelnagar/refilament/compare/v0.1.0...v0.1.1-beta.1) - 2026-08-08

**The engine — first beta.** The full MVP roadmap implemented, tested (560 tests / 1622
assertions) and shipped with a consumer-ready prebuilt bundle.

### Added

- **Panel shell** — sidebar navigation (groups, collapse persistence), brand + colors, dark mode,
  dashboard route, standalone pages (`->pages([...])`), render-hook slots, auth gate.
- **Page system** — Filament's `getPages()` model: list/create/edit/view built-ins + custom
  resource pages + pages-as-tables, one route per page name, `refilament:make-page`.
- **Tables** — server-side pagination/sorting/search/filters, column formatters + display cells
  (badge/icon/url/money/date), relationship dot-notation columns with sort/search, grouping +
  per-group subtotals, footer summaries, toggleable columns, bulk actions + soft deletes,
  selection, URL-persisted view state.
- **Forms** — text/select/textarea/checkbox/toggle/radio/date/time/number fields, dependent
  options (`dependsOn`), live debounced unique validation, visibility rules, fieldset/tabs
  layouts, computed client-side fields, hint actions.
- **Modal actions** — create/edit/delete through the typed endpoints with validation mapping.
- **Relation managers** — tabs + scoped endpoints for to-many relationships.
- **Global search** — command-palette search across resources, per-result actions + icons.
- **Widgets** — stats overview, bar/line/pie/doughnut charts with polling + per-request filters,
  table widgets.
- **Infolists** — read-only record display.
- **Notifications** — structured server payloads → sonner toasts; database-notifications bell
  with polling.
- **Authorization** — policy-backed `can*()` gates (permissive default), per-action `authorize()`.
- **i18n** — opt-in `translateLabel()` on every label seat.
- **Global configuration** — `configureUsing()` global defaults + builder macros.
- **v1 shipping** — prebuilt bundle (`npm run build:assets` → `public/refilament.{js,css}`),
  package root view, `vendor:publish` assets/migrations, generator commands.

### Fixed

- Inertia's Blade components now register in the test suite (tests boot the real provider).

## [v0.1.0](https://github.com/osamaelnagar/refilament/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
