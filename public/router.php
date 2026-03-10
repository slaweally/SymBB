<?php

/**
 * Router script for PHP built-in development server.
 *
 * PHP's built-in server tries to resolve .php URIs as physical files.
 * Since legacy MyBB files live in legacy_core/ (not public/), requests
 * like /usercp.php would 404 before reaching Symfony's front controller.
 *
 * This script intercepts ALL requests and routes them through index.php
 * unless the requested file physically exists in public/.
 *
 * Usage: php -S 127.0.0.1:8000 -t public public/router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . $uri;

// Serve existing static files directly (CSS, JS, images, etc.)
if ($uri !== '/' && is_file($publicPath)) {
    return false; // Let PHP's built-in server handle it
}

// Route everything else through Symfony's front controller
require __DIR__ . '/index.php';
