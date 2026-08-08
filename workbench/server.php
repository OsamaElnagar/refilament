<?php

declare(strict_types=1);

/**
 * Dev server router for the workbench app (php -S).
 *
 * Serves existing files from workbench/public directly (correct MIME types)
 * and routes everything else through the front controller.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
