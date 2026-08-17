<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Refilament\Refilament\Http\Controllers\DashboardController;
use Refilament\Refilament\Http\Controllers\DatabaseNotificationsController;
use Refilament\Refilament\Http\Controllers\FileUploadController;
use Refilament\Refilament\Http\Controllers\GlobalSearchActionController;
use Refilament\Refilament\Http\Controllers\GlobalSearchController;
use Refilament\Refilament\Http\Controllers\RecordPageFormController;
use Refilament\Refilament\Http\Controllers\RelationManagerController;
use Refilament\Refilament\Http\Controllers\ResourcePageActionController;
use Refilament\Refilament\Http\Controllers\SchemaDocumentController;
use Refilament\Refilament\Http\Controllers\SchemaOptionsController;
use Refilament\Refilament\Http\Controllers\SchemaSubmitController;
use Refilament\Refilament\Http\Controllers\SchemaValidationController;
use Refilament\Refilament\Http\Controllers\TableController;
use Refilament\Refilament\Http\Controllers\WidgetDataController;
use Refilament\Refilament\Http\Middleware\AppendInertiaVersion;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;

// This file is required from Refilament::registerRoutes(), which wraps it in
// the framework's `web` middleware group (sessions + CSRF + SubstituteBindings,
// mirroring Filament's `->hasRoutes('web')`) and Route::prefix(panel path) —
// every URI below is therefore relative to the panel's URL prefix, and a
// consumer's `Panel::path('admin')` moves the whole route group with it. The
// panel is fully resolved at that point (routes register from the provider's
// `booted()` hook), so the middleware list is the live one: the panel's
// `->middleware()` applies after the web group to every route, the access
// gate (PanelAuthenticate) mounts on **every** panel route — the shell pages
// (dashboard + page routes) and the typed endpoints alike, mirroring Filament,
// where the whole panel sits behind `authMiddleware()` — and
// AppendInertiaVersion guarantees the panel's Inertia responses carry the
// `X-Inertia-Version` handshake even when the consumer never appended its own
// Inertia middleware to `web`. The gate reads its config from the live panel
// per request and passes through when it isn't enabled; when it is, an
// unauthenticated shell request is redirected to the panel's loginUrl and an
// unauthenticated JSON/API request gets 401 — so the endpoints are never
// reachable without a session either.

$panel = app(Refilament::class)->panel();
$panelRouteMiddleware = [...$panel->getMiddleware(), PanelAuthenticate::class, AppendInertiaVersion::class];

// The dashboard (slice 1.9): GET /{panel path} renders the panel's registered
// widgets. An exact path, so it never collides with the auto-registered
// /{panel path}/{resource} routes (which are where()-gated to the discovered
// ids).

Route::get('/', [DashboardController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.dashboard');

Route::get('search', [GlobalSearchController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.global-search');

Route::post('search/{resource}/action/{action}', [GlobalSearchActionController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.global-search.action');

// The typed widget data endpoint (slice 3.2): chart widgets that opt into
// polling/filters refetch here — the widget is rebuilt from its registered
// resolver per request, so no state survives between requests.
Route::get('widget/{widget}/data', [WidgetDataController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.widget.data');

Route::get('schema/{schema}', [SchemaDocumentController::class, 'show'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.schema.document');

Route::post('schema/resolve-options', [SchemaOptionsController::class, 'resolve'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.schema.resolve-options');

Route::post('schema/{schema}/submit', [SchemaSubmitController::class, 'submit'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.schema.submit');

Route::post('schema/{schema}/validate', [SchemaValidationController::class, 'validate'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.schema.validate');

// The typed file-upload endpoint (the FileUpload field): the client stores a
// picked file and the submit payload carries the returned path. The disk is
// validated against the app's filesystems config, never trusted blindly.
Route::post('upload', [FileUploadController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.upload');

Route::get('table/{table}', [TableController::class, 'index'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.table.index');

Route::get('table/{table}/record/{record}', [TableController::class, 'record'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.table.record');

Route::post('table/{table}/action/{action}', [TableController::class, 'action'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.table.action');

Route::post('table/{table}/bulk/{action}', [TableController::class, 'bulk'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.table.bulk');

Route::post('table/{table}/record/{record}', [TableController::class, 'update'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.table.update');

// Inline editable column (slice: editable columns) — a single column of one
// record. The client's inline control (checkbox/switch/select/text input)
// posts the new value here; the controller resolves the table + column +
// record, checks the column is editable and authorized, validates the value
// against the column's rules, and persists via the column's update handler.
Route::post('table/{table}/record/{record}/column/{column}', [TableController::class, 'updateColumn'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.table.column');

Route::get('relation/{resource}/{record}/{relation}', [RelationManagerController::class, 'index'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.relation.index');

Route::post('relation/{resource}/{record}/{relation}/action/{action}', [RelationManagerController::class, 'action'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.relation.action');

Route::get('relation/{resource}/{record}/{relation}/record/{item}', [RelationManagerController::class, 'record'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.relation.record');

// Record-scoped page header actions (the Edit/Delete buttons on edit and
// view pages — slice: page actions on record pages). The page slot name and
// record are in the URL; the controller resolves the page class from the
// resource's getPages() map and the action by name from that page's
// getHeaderActions(). The {resource} segment is deliberately not
// where()-gated: unlike the shared page GET routes (which re-register with
// fresh ids), this route file loads once at boot, so a late-registered
// resource (tests, runtime discovery) would 404 against a stale gate. The
// controller 404s unknown resources instead, and the specific
// page/record/action segments keep the shape unambiguous.
Route::post('{resource}/page/{page}/record/{record}/action/{action}', [ResourcePageActionController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->where('record', '[0-9]+')
    ->where('page', '[a-z0-9-]+')
    ->where('action', '[a-zA-Z0-9-]+')
    ->name('refilament.resource.action');

// Submit the form of a record-scoped custom page against its record (the
// record-pages slice — `/{record}/manage` hosting a `form()`). The page
// payload serializes this URL as `submitUrl`; the controller resolves the
// page from the resource's getPages() map and the record from the URL, then
// validates + updates through the page's form schema. Same shape and
// constraints as the page-action endpoint above.
Route::post('{resource}/page/{page}/record/{record}/submit', [RecordPageFormController::class, '__invoke'])
    ->middleware($panelRouteMiddleware)
    ->where('record', '[0-9]+')
    ->where('page', '[a-z0-9-]+')
    ->name('refilament.resource.page-form');

// The database-notifications bell (slice B3): the shell polls the index for
// the unread count and latest rows, and POSTs to mark notifications read.
Route::get('notifications', [DatabaseNotificationsController::class, 'index'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.notifications.index');

Route::post('notifications/read-all', [DatabaseNotificationsController::class, 'markAllRead'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.notifications.read-all');

Route::post('notifications/{notification}/read', [DatabaseNotificationsController::class, 'markRead'])
    ->middleware($panelRouteMiddleware)
    ->name('refilament.notifications.read');
