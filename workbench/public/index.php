<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Workbench dev front controller.
 *
 * Boots the Orchestra Testbench application (skeleton base path) from the
 * package's testbench.yaml. Served by `php vendor/bin/testbench serve` after
 * WorkbenchServiceProvider points the public path at this directory.
 */
define('LARAVEL_START', microtime(true));

require __DIR__.'/../../vendor/autoload.php';

if (! defined('TESTBENCH_WORKING_PATH')) {
    $workingPath = getenv('TESTBENCH_WORKING_PATH');

    define(
        'TESTBENCH_WORKING_PATH',
        is_string($workingPath) && $workingPath !== '' ? $workingPath : dirname(__DIR__, 2),
    );
}

/** @var Application $app */
$app = require __DIR__.'/../../vendor/orchestra/testbench-core/laravel/bootstrap/app.php';

$app->handleRequest(Request::capture());
