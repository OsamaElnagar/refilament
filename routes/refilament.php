<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Refilament\Refilament\Http\Controllers\DashboardController;
use Refilament\Refilament\Http\Controllers\RelationManagerController;
use Refilament\Refilament\Http\Controllers\SchemaDocumentController;
use Refilament\Refilament\Http\Controllers\SchemaOptionsController;
use Refilament\Refilament\Http\Controllers\SchemaSubmitController;
use Refilament\Refilament\Http\Controllers\SchemaValidationController;
use Refilament\Refilament\Http\Controllers\TableController;
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;

// The dashboard (slice 1.9): GET /refilament renders the panel's registered
// widgets. An exact path, so it never collides with the auto-registered
// /refilament/{resource} routes (which are where()-gated to the discovered
// ids). A shell-rendering route, so the panel's access gate mounts here
// (Authenticate reads refilament.panel.auth_middleware per request and passes
// through when the gate isn't enabled); the API endpoints below are not gated —
// they are reachable only from a rendered shell page.
use Refilament\Refilament\Http\Middleware\Authenticate as PanelAuthenticate;

Route::get('refilament', [DashboardController::class, '__invoke'])
    ->middleware([PanelAuthenticate::class])
    ->name('refilament.dashboard');

Route::get('refilament/schema/{schema}', [SchemaDocumentController::class, 'show'])
    ->name('refilament.schema.document');

Route::post('refilament/schema/resolve-options', [SchemaOptionsController::class, 'resolve'])
    ->name('refilament.schema.resolve-options');

Route::post('refilament/schema/{schema}/submit', [SchemaSubmitController::class, 'submit'])
    ->name('refilament.schema.submit');

Route::post('refilament/schema/{schema}/validate', [SchemaValidationController::class, 'validate'])
    ->name('refilament.schema.validate');

Route::get('refilament/table/{table}', [TableController::class, 'index'])
    ->name('refilament.table.index');

Route::get('refilament/table/{table}/record/{record}', [TableController::class, 'record'])
    ->name('refilament.table.record');

Route::post('refilament/table/{table}/action/{action}', [TableController::class, 'action'])
    ->name('refilament.table.action');

Route::post('refilament/table/{table}/bulk/{action}', [TableController::class, 'bulk'])
    ->name('refilament.table.bulk');

Route::post('refilament/table/{table}/record/{record}', [TableController::class, 'update'])
    ->name('refilament.table.update');

Route::get('refilament/relation/{resource}/{record}/{relation}', [RelationManagerController::class, 'index'])
    ->name('refilament.relation.index');

Route::post('refilament/relation/{resource}/{record}/{relation}/action/{action}', [RelationManagerController::class, 'action'])
    ->name('refilament.relation.action');

Route::get('refilament/relation/{resource}/{record}/{relation}/record/{item}', [RelationManagerController::class, 'record'])
    ->name('refilament.relation.record');
