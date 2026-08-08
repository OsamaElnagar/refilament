<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\HomeController;
use Workbench\App\Http\Controllers\PlaygroundController;
use Workbench\App\Http\Controllers\WidgetsOverviewController;

Route::get('/', HomeController::class);

// The list, create, edit and view pages for the discovered resources
// (posts, users) need no hand-written routes - the package auto-registers
// one route per entry in each resource's getPages() map (slice 1.6):
// /refilament/{resource}, /{resource}/create, /{resource}/{record}/edit
// and /{resource}/{record}, all where()-gated to the discovered ids. Only
// app-specific pages stay here.

Route::get('/refilament/playground', PlaygroundController::class);

// Free-standing demo page hosting the stats overview widget (slice 3.1) -
// the package's widget layer is a JSON snapshot served by any app page.
Route::get('/refilament/widgets', WidgetsOverviewController::class);
