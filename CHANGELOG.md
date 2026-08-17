# Release Notes

## [Unreleased](https://github.com/osamaelnagar/refilament/compare/v0.2.3...main)

## [v0.2.3](https://github.com/osamaelnagar/refilament/compare/v0.2.2...v0.2.3) - 2026-08-17

### Added

- **Long-text columns no longer stretch the table** — `refilament:make-resource` now emits `->lineClamp(2)` on `text`/`longtext` columns, so generated tables clamp the body to a couple of lines instead of rendering it full-width. The full value is preserved in the payload (clamping is client-side), and the cell shows it on hover.
- **Text-cell reveal options (`Column`)** — four client-side modifiers that keep the full value in the payload and reveal it without a round trip:
  - `->expandable(int $lines = 2)` — clamp to N lines and reveal the rest in place with an inline "Show more / Show less" toggle (only shown when the text actually overflows).
  - `->previewOnClick()` — a small expand icon on the cell opens the full value in a dialog.
  - `->copyable()` — a copy-to-clipboard icon on the cell.
  - `->wrap()` — let the value wrap inside the cell instead of forcing the column wider (implicit when clamped/expandable).
  - Any `->tooltip()` now renders as a styled shadcn tooltip; a clamped cell without an explicit tooltip shows its full value on hover automatically.

## [v0.2.2](https://github.com/osamaelnagar/refilament/compare/v0.2.1...v0.2.2) - 2026-08-17

### Added

- **`refilament:make-resource` ships actions by default** — generated tables now carry per-row `recordActions([EditAction, DeleteAction])`, the generated edit page carries `ViewAction` + `DeleteAction` header actions, and the generated view page carries `EditAction` + `DeleteAction` header actions. The needed `use` statements are emitted alphabetized (pint-clean), and the list page keeps its default `CreateAction`.

## [v0.2.1](https://github.com/osamaelnagar/refilament/compare/v0.2.0...v0.2.1) - 2026-08-17

### Fixed

- **`refilament:make-resource` generated tables with boolean columns failed to load** — the generator emitted `use Refilament\Refilament\Tables\ToggleColumn;`, but the class lives under `Refilament\Refilament\Tables\Columns\ToggleColumn`. The import (and the test that encoded the wrong namespace) now point at the real class, so generated `ToggleColumn` tables resolve at runtime.

## [v0.2.0](https://github.com/osamaelnagar/refilament/compare/v0.1.1-beta.2...v0.2.0) - 2026-08-17

### Added

- **Render-hook slots (B1)** — `Panel->renderHook(PanelsRenderHook::*, Closure|string)` mirrors Filament's `renderHook(PanelsRenderHook::..., view)`. A new `Support\Enums\PanelsRenderHook` enum mirrors Filament's slot values (`panels::sidebar.footer`, `panels::topbar.end`, `panels::page.start`, …); `Panel->renderHook()` accepts a case (or a raw custom string) and a HTML string or closure returning one (commonly a Blade view) — evaluated to its final HTML per request, shipped in the payload as `renderHooks: { slot: html }`. The React shell injects each armed slot's HTML at the fixed positions (`ShellSlot` — sidebar footer/start/nav, topbar start/end/before/after, page/content start/end/before/after, layout start/end, footer, user menu, global search). A consumer provides their own Blade/HTML with no JS bundle; the demo app arms three hooks with Blade views. React-component hooks are a separate, planned extension (`docs/PLAN_REACT_COMPONENT_HOOKS.md`).

- **`Wizard` layout** (multi-step form, PLAN Wizard). `Wizard::make()->steps([WizardStep::make('Basics')->description(...)->icon(...)->schema([...])])` serializes a `wizard` node whose children are `wizard-step` nodes (`label`, optional `description`/`icon`, and `schema`); the client renders a numbered step indicator (completed steps tick, skippable steps are clickable) and shows only the active step's fields with Back/Next navigation. The active step is pure client state — navigating never hits the server and the whole form submits together. `startOnStep(int)` (1-indexed, omitted at 1) and `skippable(bool)` emit only when set. Workbench demo: a skippable three-section wizard (Basics / Publishing) at the end of the schema playground. `WizardTest`, 8 unit tests + a playground feature assertion.
- **`RepeatableEntry`** (read-only infolist entry, PLAN §3). `RepeatableEntry::make('items')->schema([TextEntry::make('name'), ...])` renders the state (an array of items - a JSON column, a relation, or a `getStateUsing()` result) as a bordered card per item, each item's child entries resolved server-side against that item's own array/model data via `data_get`. The shipped node carries `items` (a list of per-item child-entry node-lists), `label`, optional `placeholder`, and `columnSpan`. `Entry::resolveRawState` is now array-safe so child entries bind to array items. Workbench demo: a word-breakdown `RepeatableEntry` on the PostResource infolist; each word + its length renders as a card.
- **`Entry::resolveRawState` array-safe**: when the bound record is an array/`ArrayAccess` (a RepeatableEntry item), the entry value resolves through `data_get` instead of `getAttribute()`.
- **`refilament:make-resource` aligned with Filament's generator surface**: the command now exposes `getArguments()`/`getOptions()` with `--generate`/`-G`, `--force`/`-F`, `--model`, `--model-namespace`, `--view`, `--soft-deletes` and `--record-title-attribute`. It auto-detects the record title from a `title`/`name` column (emitting `$recordTitleAttribute` on the resource) and the model's `SoftDeletes` trait (emitting a `TrashedFilter` in the generated table). `--view` additionally generates a read-only `{Name}Infolist` schema wired into `Resource::infolist()`. `--generate` mapping improved: boolean columns become `ToggleColumn`/`Toggle`, text columns `Textarea`, and non-skipped datetime/timestamp columns `DateTimePicker`/`DatePicker`/`TimePicker`. Only the `use` statements the generated bodies reference are emitted, so the output is pint-clean out of the box.

### Changed

- **`BooleanColumn` deprecated in favour of `IconColumn->boolean()`** (mirrors
  Filament v5). `IconColumn` gains the boolean machinery: `boolean()`, `true()` /
  `false()` (icon + colour in one call), `trueIcon()` / `trueColor()` /
  `falseIcon()` / `falseColor()` (each enabling boolean mode), with state-driven
  defaults — truthy → `check-circle` / `success`, falsy → `x-circle` / `danger`.
  Boolean cells serialize `{ value: 'Yes'|'No', icon, iconColor }` per record;
  null states ship `null` (cell placeholder). `BooleanColumn` remains as a
  deprecated subclass serializing the same shape. Workbench demo: the comments
  relation table's `is_visible` column now renders the boolean presentation.

- **Validation API aligned with Filament's `CanBeValidated`** — `validation()` is
  deprecated in favour of `rules()`, which is *additive* (stacking calls appends rather
  than replaces) and accepts pipe-separated strings, arrays of strings / `Rule` objects /
  Laravel closure rules, or a closure that returns the rules. New `rule()` adds a single
  rule (a `Closure` there is a Laravel closure rule, passed through untouched).
  `required()` now takes `bool | Closure` — `->required(fn (Get $get) => $get('type') ===
  'physical')` — evaluated against the form's data snapshot via the new `Schemas\Get`
  injection: submitted values at validation time, initial values at serialization
  (per-request, stateless — no persistent Livewire component). Non-required fields with
  rules now fold in Laravel's `nullable` base rule server-side (mirroring Filament's
  `getRequiredValidationRule()`), so empty input never trips type rules; the client
  payload omits it (and `validation` now ships only the string subset, never closures /
  `Rule` objects). `Schema::setValidationData()` pushes the snapshot to every component
  including repeater row fields.

### Added

- **Inline-editable columns (PLAN §3 — first slice)** — a column can now be
  edited in place from the table row. The client renders a control
  (checkbox / switch) that writes one column of one record through a new
  typed endpoint `POST table/{table}/record/{record}/column/{column}`
  (`{ value: ... }`); a stateless request/response, the honest rebuild of
  Filament's Livewire inline edits — no persistent component, the client
  builds the URL from the table id + row id + column name. `Column` gains
  `editable()`, `canEdit()` (per-record authorization), `rules()` (server-side
  validation enforced by the endpoint) and `updateStateUsing()` (custom
  persistence; default mass-assigns to the named attribute); editable columns
  ship `editable: true` on their definition. New columns:
  - **`CheckboxColumn`** — editable by default, `onColor()` / `offColor()`,
    serializes `{ value: bool }`.
  - **`ToggleColumn`** — editable by default, `onColor()` / `onIcon()` /
    `offIcon()`, serializes `{ value: bool }`.
  The endpoint returns the fresh cell value so the client reconciles its
  optimistic update; validation / authorization failures return the proper
  status (422 / 403) and the client refetches to restore true state. Workbench
  demo: the posts table's `published` toggle (maps the `status` string through
  a custom `updateStateUsing()`).
  - **`SelectColumn`** — an inline-editable native `<select>`: `options()`
    (a `value => label` map or a closure), `placeholder()`, and
    `disabledOption()` / `disabledOptions()` (per-option disabling, shipped as
    `isDisabled`). While editable the cell ships the raw value so the dropdown
    can set itself; `editable(false)` falls back to the option label. Posts
    demo: `Set Status` (maps the `status` string through a custom
    `updateStateUsing()`).
  - **`TextInputColumn`** — an inline-editable text input that commits on
    Enter/blur (Escape reverts): `type()`, `inputMode()`, `step()`, and
    `maxLength()` (ships a `maxlength` attribute *and* appends Laravel's
    `max:{n}` rule). The cell ships the raw attribute value. Posts demo:
    `Edit Views` (a number input casting to an integer via a custom
    `updateStateUsing()`).

- **More infolist entries (read-only "entry gallery", PLAN §2)** — five new
  entry types, each a PHP builder → JSON node → React renderer slice, mirroring
  Filament's pure-data config surface (no Livewire closures):
  - **`color_entry`** — `ColorEntry::make(...)->copyable()` and
    `->copyableState()` (a plain scalar override of the copied value) /
    `->copyMessage()`; the value is the color(s) rendered as copyable swatches.
  - **`image_entry`** — `ImageEntry` with `size()`, `circular()`, `square()`,
    `stacked()`, `ring()`, `limit()`; the value is a URL or list of URLs
    (URLs only for v1 — disk-backed files deferred). `limit()` overrides the
    entry's inherited text-truncation `limit()` as an image-count cap.
  - **`key_value_entry`** — `KeyValueEntry` with `keyLabel()` / `valueLabel()`
    (and the `emptyMessage()` alias of `placeholder()`); the value (an assoc
    map or a list of `{ key, value }` rows) is normalized server-side to the
    same row shape the KeyValue field uses.
  - **`code_entry`** — `CodeEntry` with `language()` (a plain label),
    `lineNumbers()`, `copyable()`; renders a `<pre><code>` block (highlighting
    deferred) and pretty-prints array values as JSON.
  - **`view_entry`** — `ViewEntry::make($key)->viewData([...])`, the infolist
    counterpart of the form tree's `View` node: the client resolves the key
    through `registerViewComponent()` and passes `viewData` as props.
  Workbench demo: PostResource's infolist (`/refilament/posts/{id}`) gains the
  entry gallery. Acceptance: `ColorEntryTest` / `ImageEntryTest` /
  `KeyValueEntryTest` / `CodeEntryTest` / `ViewEntryTest` unit tests.
- **New field types (form input slices)** — five more `schemas` fields, each a full
  PHP builder → JSON node → React renderer slice, mirroring Filament's config API where
  it is pure data:
  - **Date/time pickers** — `DateTimePicker`, `DatePicker` (date only) and `TimePicker`
    (time only). Config: `format()`, `displayFormat()`, `minDate()` / `maxDate()`
    (appending `after_or_equal` / `before_or_equal` at config time), `disabledDates()`,
    `firstDayOfWeek()` / `weekStartsOnMonday()` / `weekStartsOnSunday()`, `hoursStep()` /
    `minutesStep()` / `secondsStep()`, `timezone()`, `locale()`, `closeOnDateSelection()`.
    `getType()` = `date_time_picker`; the node always ships a resolved
    `format` / `displayFormat` / `inputType`. The React picker is a Base UI `Popover` with
    **no external date library**: a token parser reads PHP state formats and emits day.js-style
    display, plus a calendar month grid, min/max/disabled logic and hour/minute/second steppers.
  - **`TagsInput`** — `reorderable()`, `separator()` (always serialized, default `,`),
    `splitKeys()`, `suggestions()`, `tagPrefix()`, `tagSuffix()`. React: tag badges with
    add-on-Enter/comma and split keys, remove, Backspace-deletes-last, clickable suggestions,
    HTML5 drag-to-reorder. Value is an array of strings.
  - **`ToggleButtons`** — an `options()`-based segmented control: `multiple()` (state becomes
    an array), `inline()`, `grouped()`, `hiddenButtonLabels()`, per-option
    `icons()` / `colors()` / `tooltips()`, and a `boolean()` preset (labelled `1`/`0` with
    success/danger colors). React renders a radio or checkbox group of buttons. The preset
    drove widening `Component::options()` to accept int keys — option values genuinely include
    boolean `1`/`0`.
  - **`ColorPicker`** — `format()` (`hex` / `hsl` / `rgb` / `rgba`, default `hex`) with
    `hex()` / `hsl()` / `rgb()` / `rgba()` conveniences. React: a text input + preview swatch +
    popover picker (hue/lightness square, hue + alpha sliders, preset swatches) with a
    dependency-free color parser/emitter. Value is the color string in the chosen format.
  - **`KeyValue`** — an editable table of `{ key, value }` rows: `addable()` / `deletable()`,
    `editableKeys()` / `editableValues()`, `reorderable()`, `keyLabel()` / `valueLabel()`,
    `keyPlaceholder()` / `valuePlaceholder()`, `addActionLabel()`. Default-true flags serialize
    as omitted (minimal payload). React: add/remove rows, drag-to-reorder, per-cell edit locking.
  - Acceptance: new unit suites (`DateTimePickerTest`, `TagsInputTest`, `ToggleButtonsTest`,
    `ColorPickerTest`, `KeyValueTest`), full `phpstan` + `pint` + `tsc` clean.

- **Specialized table column kinds (Tags / Image / Color)** — three read-only columns,
  each a PHP class → structured cell → React primitive slice, mirroring Filament v5:
  - **`TagsColumn`** — the array state renders as a badge list. `limitList(N)` caps the
    badges and ships the overflow count. Cell `{ value, tags, remaining? }`.
  - **`ImageColumn`** — the URL(s) render as thumbnails. `imageSize()`, `circular()`,
    `square()`, `stacked()` with `ring()` / `overlap()`, `limit()` with
    `limitedRemainingText()`, `alt()`, `defaultImageUrl()`. Cell `{ value, images,
    remaining? }`. Deferred: disk-backed storage (URLs/paths only).
  - **`ColorColumn`** — rewritten from colored-text to a color-swatch renderer (Filament v5
    semantics). `copyable()` makes a swatch copy its value on click. Cell `{ value, colors }`.
  - The shared `cell.tsx` gains three primitives (badge list, stacked/circular image stack,
    copyable color swatches) dispatched by a `kind` discriminator on the column definition;
    blank states still fall through to the placeholder. Acceptance: new unit suites
    (`TagsColumnTest`, `ImageColumnTest`, `ColorColumnTest`), full `phpstan` + `pint` +
    `tsc` clean, assets rebuilt and republished. Workbench posts table demos all three.

- **Repeater row-management upgrade** — the `repeater` field gains the full pure-data,
  client-side row-management surface. New config (mirroring Filament): `addable()` /
  `deletable()` (defaults on), `cloneable()` (duplicate a row), `reorderable()` with
  `reorderableWithDragAndDrop()` / `reorderableWithButtons()`, `itemNumbers()` (numbered
  headings), `itemHeaders()`, `collapsed()` (start every row folded), and `itemLabel()`
  now accepts a `{field}` token template substituted from each row's state (closures stay
  off the wire — the client evaluates it). React: stable per-row ids so reorder/clone/
  remove keep their collapsed state, HTML5 drag reorder, up/down buttons, clone,
  collapse-all/expand-all, min/max enforced on add/remove, and the dynamic grid-columns
  classes fixed via a literal lookup map (Tailwind v4). Acceptance: `RepeaterTest` now 10
  tests (row-management toggles, reorder modes, collapsed start, defaulted-key omission),
  full `phpstan` + `pint` + `tsc` clean, assets rebuilt and republished.

- **Page infolists** — any page (standalone or custom resource page) now hosts a
  read-only record display by declaring `infolist(Schema $schema): Schema` (the
  same override point a resource uses). Entries resolve their values server-side
  at payload time from the record the page reads — the URL record on a
  record-scoped page, or a standalone page's own `getInfolistRecord()` — and the
  generic `refilament/page-infolist` component renders them (heading, breadcrumbs,
  header actions, widgets). Zero consumer React code.

- **Record-scoped custom pages (`/{record}/manage`)** — a custom resource page
  registering a `{record}` path now gets first-class support: `resolveRecord()`
  resolves the `{record}` segment through the page's record-binding query and
  gates it with the resource policy (404/403), and the form/infolist payload
  serializers bind the URL record — a form page pre-fills from the record and
  ships a `submitUrl` pointing at a new typed endpoint
  (`POST {resource}/page/{page}/record/{record}/submit`) that validates against
  the page's form rules (unique rules ignoring the record) and updates it,
  returning fresh values with a default `'Saved.'` toast. No fake Livewire:
  state stays client-held, every submit re-validates server-side.

- **CLI** — `refilament:make-page --infolist` scaffolds a standalone infolist
  page; `--record` scaffolds a complete record-scoped resource page
  (`/{record}/manage` with `resolveRecord` wired). Combining `--infolist` with
  `--form`/`--table`, or `--record` without `--resource`, is rejected.

### Changed

- **One unified `Refilament\Refilament\Actions` namespace** — every action now lives
  in a single namespace, mirroring Filament v5 where table, page, header and notification
  actions all come from `Filament\Actions` (the tables package imports them from there;
  even `Filament\Notifications\Notification` attaches `Filament\Actions\Action`).
  `Action` (base), `BulkAction`, `ActionGroup`, `CreateAction`, `EditAction`, `ViewAction`,
  `DeleteAction`, `DeleteBulkAction`, `ForceDeleteBulkAction` and `RestoreBulkAction` all
  live under `Refilament\Refilament\Actions`, with the shared `CanBeAuthorized` trait at
  `Actions\Concerns`.

  **Breaking:** the old `Refilament\Refilament\Tables\Action`, `Tables\BulkAction` and
  `Tables\Actions\*` locations are gone (no aliases, like Filament's own major-version
  removal). Generated resources, tests, the workbench and the demo app were migrated.

- **`EditAction` added** — the record edit counterpart of `ViewAction` (pencil icon,
  primary color, `update` policy gate, per-record link to the resource's edit page via the
  table's URL resolver), so `EditAction::make()` works everywhere `ViewAction::make()`
  does, straight from the unified namespace.

### Added

- **Page forms — any page can host a form** (the first slice of the custom-pages
  program, mirroring Filament's `InteractsWithForms`). A standalone page or a custom
  resource page declares `form(Schema $schema): Schema` on its class and the whole
  pipeline wires itself:
  - The schema document (fields + rules + `data` + `errors`) serializes into the
    page payload via `Pages\Page::serializePageForm()` — merged by `PanelPageController`
    for standalone pages and `Resources\Pages\Page::getPayload()` for resource pages
    (the built-in record pages declare no `form()`, so their own create/edit pipelines
    stay untouched).
  - The page's form registers a schema resolver under `getFormId()` (derived from the
    class, so page form ids never collide) — the existing typed submit / validate
    endpoints serve it, so state is client-held, rules validate server-side, and the
    schema's `submitUsing()` persists the validated data.
  - `getFormData()` seeds the starting values (defaults to the fields' `default()`s;
    a page bound to a record — the settings-page / singular-resource idiom — overrides
    it to load that record); `getFormSubmitLabel()` names the submit button;
    `$hasUnsavedDataChangesAlert` (default off) prompts before navigating away with
    dirty data.
  - The generic `refilament/page-form` React page renders the whole thing — heading,
    breadcrumbs, header actions, widgets, the form with submit, and the unsaved-changes
    guard (dirty tracking + `before`-event interception with a confirm dialog; a
    successful save reloads the page for fresh server-derived values). Zero consumer
    React code for a form page.
  - `refilament:make-page --form` scaffolds a standalone page with the form surface
    pre-wired.
  - Workbench: the Settings page became the demo (a profile form bound to the
    authenticated user — name/email, validation, save). Demo app: a Settings page
    under `/admin/settings` with the same form, browser-verified end to end (render,
    pre-fill, unsaved-changes guard, save + toast, server 422 on invalid input).
  - Acceptance: `tests/Feature/PageFormsTest.php` (standalone + resource-page form
    serialization, submit round-trip through the typed endpoint, server-side
    validation, payload omission for form-less pages) + updated `PanelPagesTest` +
    `MakePageCommandTest`. Full suite **673 tests / 2061 assertions**, phpstan +
    pint + `tsc` clean.

- **Pages hosting tables — pages-as-tables** (the second slice of the
  custom-pages program, mirroring Filament's `InteractsWithTable`). A
  standalone page or a custom resource page declares
  `table(Table $table): Table` on its class and the whole pipeline wires
  itself:
  - `Pages\Page::serializePageTable()` ships exactly what a resource list
    page ships — the table definition plus the first page of rows — plus
    `tableTitle` (the page's own title for the heading). Merged by
    `PanelPageController` for standalone pages and
    `Resources\Pages\Page::getPayload()` for resource pages (the built-in
    record pages declare no `table()`, so their own pipelines stay
    untouched).
  - The page's table registers a resolver under `getTableId()` (the same
    class-derived derivation as `getFormId()`) — the existing typed table
    endpoints (index, record action, bulk, update) rebuild the table per
    request, so pagination, sorting, search, filters and record/bulk
    actions all run server-side, exactly like resource tables.
  - The generic `refilament/page-table` React page renders the whole thing
    — heading, description, breadcrumbs, header actions, widgets and the
    table via `TableRenderer`. Zero consumer React code for a table page.
  - `refilament:make-page --table` scaffolds a standalone page with the
    table surface pre-wired (`--form` and `--table` together are rejected).
  - Workbench: the new Posts table page (`/refilament/posts-table`) — a
    posts table with search, sorting and a status filter. Demo app: the
    Inventory page (`/admin/inventory`) — a products table (name, category,
    SKU, price + footer total, stock, status badge) with a status filter,
    browser-verified end to end (render from the payload, search refetches
    through the typed endpoint).
  - Acceptance: `tests/Feature/PageTablesTest.php` (standalone page table
    serialization, typed-endpoint round trip with server-side pagination,
    custom resource-page table merged into `getPayload` + endpoint, payload
    omission for table-less pages) + updated `MakePageCommandTest`. Full
    suite **679 tests / 2108 assertions**, phpstan + pint + `tsc` clean.

- **Singular resources — one record, auto-created on first save** (the third
  slice of the custom-pages program, mirroring Filament's documented
  "singular resource" pattern). There is no special class in Filament — it's
  a custom page with a form bound to one record — so ours is a first-class
  scaffold around the same idea:
  - `Pages\Page` gains the singular surface: `$model` + `getModel()`,
    `getRecordQuery()` (override to scope which record, e.g.
    `->where('is_homepage', true)`), and `getRecord()` (the first matching
    row). Declaring `$model` wires the whole pattern:
    - `getFormData()` loads the record's values for the form's fields — the
      form opens holding the record, and opens with the field defaults when
      no record exists yet (Filament's `mount()` + `fill()`).
    - `getFormSchema()` auto-registers a **create-or-update submit handler**
      when the page declares no `submitUsing()` of its own: the first submit
      creates the record, every later one updates it. The validation rules
      ignore the record's own unique values (a settings record never rejects
      itself), via a new `Schema::ignoreCurrentRecord()` that
      `getValidationRules()` applies — and a `'Saved.'` default success
      message ships when none was declared. Boot-time route registration
      stays database-free through the new cheap `hasFormSchema()` gate.
  - `refilament:make-singular-resource {name} {--model=}` scaffolds a
    standalone page with `$model` wired, the unsaved-changes alert on, and
    sample fields — zero consumer React code (renders
    `refilament/page-form`).
  - Demo app: the Site settings page (`/admin/site-settings`) backed by a
    new `SiteSetting` model + migration — empty on first visit, the first
    save creates the row, later saves update it. Browser-verified end to
    end (empty form → save → toast → reload pre-fills → second save updates
    the same row).
  - Acceptance: `tests/Feature/SingularResourceTest.php` (empty form when
    no record exists, first-save auto-create, later visits load the record,
    update-without-duplicate, unique-rule ignore scoped to the record's own
    values, generator + unknown-model rejection). Full suite **686 tests /
    2142 assertions**, phpstan + pint + `tsc` clean.

- **Record-scoped page header actions** — Filament's page-vs-modal semantics for the
  Edit/View/Delete built-ins on a resource's edit and view pages. On record pages the
  header-action serializer now resolves `EditAction`/`ViewAction` to per-record page URLs
  through the resource's page map + policy gates (the button navigates when the page
  route exists and the user may act on the record, and is dropped otherwise — never
  rendered dead), and server actions (`DeleteAction` …) serialize a runnable endpoint
  plus the record key, with `DeleteAction` carrying the list-page URL to land on after
  deleting. A new typed endpoint
  (`POST /{path}/{resource}/page/{page}/record/{record}/action/{action}`,
  `ResourcePageActionController`) re-checks visibility and runs the action's closure;
  the client confirms when required, POSTs, toasts, then follows the redirect (or
  reloads).

### Fixed

- **Standalone pages behind the auth gate on Laravel 13** — the shared `{page}` route
  (serving `->pages([...])` standalone pages) was registered with two chained
  `->middleware()` calls. On Laravel 13 the `RouteRegistrar`'s `attribute()`
  **replaces** rather than merges, so the second call dropped the `web` group — and
  with it `StartSession`. The auth gate then saw no session and rejected every
  request, bouncing `/admin/settings`-style pages in a redirect loop (the panel login
  then bounced to the app dashboard). The standalone page route now carries the same
  combined middleware stack as the dashboard and typed endpoints (`web` + panel
  middleware + gate + version middleware), so the panel's session-based auth works on
  standalone pages. Regression test in `PanelPagesTest`.

- **Shell user menu** — the authenticated user's dropdown in the AppShell header (avatar
  initials + name/email), linking to the panel's Profile page (`->profile()`) and Two-factor
  settings page when those features are enabled, plus Logout — a POST to `/{{path}}/logout` that
  lands back on the panel's login page via the package's `LogoutResponse` (Fortify's default
  redirects to the app root; a consumer's own `LogoutResponse` binding always wins). The shell
  reads the user's name/email from a new `refilament.user` shared prop (absent for guests, so the
  menu simply doesn't render) and each account URL from the panel payload (`profileUrl` /
  `twoFactorUrl` / `logoutUrl`, shared only when the route behind it exists).

- **Two-factor management page** — the panel's own enable/disable/QR/secret-key/recovery-codes
  UI at `/{{path}}/user/two-factor` (the authenticated `TwoFactorSettings` page inside the shell,
  served by a dedicated controller behind the panel auth guard), using Fortify's management
  endpoints with the `ConfirmPasswordDialog` component (handles 423/422/201 lifecycle, POSTs to
  `/user/confirm-password`, runs the guarded action on success) and a fallback
  `auth/confirm-password` Inertia page bound via `Fortify::confirmPasswordView(...)`. Auto-armed
  when `->twoFactorAuthentication()` is enabled.

- **Profile page (Filament's `->profile()`)** — `EditProfile` at `/{{path}}/user/profile`
  with Profile Information (name/email), Update Password, and an embedded two-factor
  authentication section (when the panel has 2FA enabled). Ships first-party default
  `UpdateUserProfileInformation` and `UpdateUserPassword` actions (bound only when unbound).
  The 2FA management cards were extracted into a shared `TwoFactorSection` React component
  reused by both the standalone `TwoFactorSettings` page and the `EditProfile` page.

- **First-party auth pages (Fortify-backed)** — the panel's own login / register /
  forgot-password / reset-password / email-verification / two-factor-challenge pages under the
  panel path, enabled by `Panel->login()` / `registration()` / `passwordReset()` /
  `emailVerification()` / `twoFactorAuthentication()` (each overridable with a consumer page class,
  Filament's `->login(MyLoginPage::class)` story). Fortify's controllers power the flows
  (rate limiting, 2FA challenge, password broker, email verification); the package owns the routes
  (`routes/auth.php`, `web` group, Inertia version header, no auth gate) and ships first-party
  default `CreatesNewUsers` / `ResetsUserPasswords` actions (bound only when unbound, so a
  consumer's Fortify bindings win). React pages use the vendored shadcn kit in a centered auth
  layout. Enabling `->login()` points the auth gate's default redirect at the panel's own login page.
- **Inertia version handshake on panel routes** — `AppendInertiaVersion` middleware rides every panel
  route and sets `X-Inertia-Version` (from the global Inertia version, else an xxh128 of the compiled
  assets manifest), so the panel's Inertia responses carry the version even when the consumer doesn't
  append `HandleInertiaRequests` to `web`; a consumer's own header stays authoritative.
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

- Row selection checkboxes are clickable squares again, end to end: the shared Base UI
  Checkbox rendered as a 2px vertical line in a table cell because it had no `display`
  style — an inline `<span>` ignores `size-4`'s width/height, so the box collapsed to its
  two side borders (now `inline-flex`, a proper 16×16 box everywhere, forms included). And
  the row click handler's interactive-element guard (buttons, links, inputs, …) missed
  Base UI's `<span role="checkbox">`, so a mouse click on the box bounced to the record
  page instead of selecting the row, making bulk actions unreachable on any
  record-navigating table. `[role="checkbox"]` now joins the guard (both surfaced by
  exercising the demo's bulk actions).
- The dropdown filter layout now hosts its panel in a Base UI `Popover` instead of a
  hand-rolled div with a document-wide `mousedown` close handler — that handler treated
  clicks inside a filter control's own portaled popup (e.g. a Base UI `Select` option list)
  as "outside" and unmounted the panel mid-click, so picking an option closed the panel and
  the selection was lost. Base UI nests the Select's layer under the Popover's, so the panel
  stays open and the filter applies (verified against the running app).
- Inertia's Blade components now register in the test suite (tests boot the real provider).

## [v0.1.0](https://github.com/osamaelnagar/refilament/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
