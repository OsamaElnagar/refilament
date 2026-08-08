<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Refilament\Refilament\Http\Controllers\DashboardController;
use Refilament\Refilament\Http\Controllers\DatabaseNotificationsController;
use Refilament\Refilament\Http\Controllers\GlobalSearchActionController;
use Refilament\Refilament\Http\Controllers\GlobalSearchController;
use Refilament\Refilament\Http\Controllers\RelationManagerController;
use Refilament\Refilament\Http\Controllers\SchemaDocumentController;
use Refilament\Refilament\Http\Controllers\SchemaOptionsController;
use Refilament\Refilament\Http\Controllers\SchemaSubmitController;
use Refilament\Refilament\Http\Controllers\SchemaValidationController;
use Refilament\Refilament\Http\Controllers\TableController;
use Refilament\Refilament\Http\Controllers\WidgetDataController;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;
use Refilament\Refilament\Refilament;

// This file is required from Refilament::registerRoutes(), which wraps it in
// Route::prefix(panel path) — every URI below is therefore relative to the
// panel's URL prefix, and a consumer's `Panel::path('admin')` moves the whole
// route group with it. The panel is fully resolved at that point (routes
// register from the provider's `booted()` hook), so the middleware list is the
// live one: the panel's `->middleware()` applies to every route, and the
// access gate (PanelAuthenticate) mounts on the shell-rendering routes — the
// dashboard and the page routes — which read the gate's config from the live
// panel per request and pass through when it isn't enabled. The API endpoints
// below are not gated — they are reachable only from within a rendered shell
// page.

$panel = app(Refilament::class)->panel();
$panelMiddleware = $panel->getMiddleware();

// The dashboard (slice 1.9): GET /{panel path} renders the panel's registered
// widgets. An exact path, so it never collides with the auto-registered
// /{panel path}/{resource} routes (which are where()-gated to the discovered
// ids). A shell-rendering route, so the panel's access gate mounts here.

Route::get('/', [DashboardController::class, '__invoke'])
    ->middleware([...$panelMiddleware, PanelAuthenticate::class])
    ->name('refilament.dashboard');

Route::get('search', [GlobalSearchController::class, '__invoke'])
    ->middleware($panelMiddleware)
    ->name('refilament.global-search');

Route::post('search/{resource}/action/{action}', [GlobalSearchActionController::class, '__invoke'])
    ->middleware($panelMiddleware)
    ->name('refilament.global-search.action');

// The typed widget data endpoint (slice 3.2): chart widgets that opt into
// polling/filters refetch here — the widget is rebuilt from its registered
// resolver per request, so no state survives between requests.
Route::get('widget/{widget}/data', [WidgetDataController::class, '__invoke'])
    ->middleware($panelMiddleware)
    ->name('refilament.widget.data');

Route::get('schema/{schema}', [SchemaDocumentController::class, 'show'])
    ->middleware($panelMiddleware)
    ->name('refilament.schema.document');

Route::post('schema/resolve-options', [SchemaOptionsController::class, 'resolve'])
    ->middleware($panelMiddleware)
    ->name('refilament.schema.resolve-options');

Route::post('schema/{schema}/submit', [SchemaSubmitController::class, 'submit'])
    ->middleware($panelMiddleware)
    ->name('refilament.schema.submit');

Route::post('schema/{schema}/validate', [SchemaValidationController::class, 'validate'])
    ->middleware($panelMiddleware)
    ->name('refilament.schema.validate');

Route::get('table/{table}', [TableController::class, 'index'])
    ->middleware($panelMiddleware)
    ->name('refilament.table.index');

Route::get('table/{table}/record/{record}', [TableController::class, 'record'])
    ->middleware($panelMiddleware)
    ->name('refilament.table.record');

Route::post('table/{table}/action/{action}', [TableController::class, 'action'])
    ->middleware($panelMiddleware)
    ->name('refilament.table.action');

Route::post('table/{table}/bulk/{action}', [TableController::class, 'bulk'])
    ->middleware($panelMiddleware)
    ->name('refilament.table.bulk');

Route::post('table/{table}/record/{record}', [TableController::class, 'update'])
    ->middleware($panelMiddleware)
    ->name('refilament.table.update');

Route::get('relation/{resource}/{record}/{relation}', [RelationManagerController::class, 'index'])
    ->middleware($panelMiddleware)
    ->name('refilament.relation.index');

Route::post('relation/{resource}/{record}/{relation}/action/{action}', [RelationManagerController::class, 'action'])
    ->middleware($panelMiddleware)
    ->name('refilament.relation.action');

Route::get('relation/{resource}/{record}/{relation}/record/{item}', [RelationManagerController::class, 'record'])
    ->middleware($panelMiddleware)
    ->name('refilament.relation.record');

// The database-notifications bell (slice B3): the shell polls the index for
// the unread count and latest rows, and POSTs to mark notifications read.
Route::get('notifications', [DatabaseNotificationsController::class, 'index'])
    ->middleware($panelMiddleware)
    ->name('refilament.notifications.index');

Route::post('notifications/read-all', [DatabaseNotificationsController::class, 'markAllRead'])
    ->middleware($panelMiddleware)
    ->name('refilament.notifications.read-all');

Route::post('notifications/{notification}/read', [DatabaseNotificationsController::class, 'markRead'])
    ->middleware($panelMiddleware)
    ->name('refilament.notifications.read');
