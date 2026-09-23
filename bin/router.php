<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in server, used only in development.
 *
 * It stands in for the .htaccess rules so `php -S` behaves like Apache does:
 *
 *   php -S 127.0.0.1:8765 -t . bin/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// Never serve what Apache blocks.
if (preg_match('#^/(\.git|src|templates|db|bin|tests|docs)(/|$)#', $path) || str_ends_with($path, '.sql')) {
    http_response_code(404);
    exit('Not found');
}

if ($path === '/es' || $path === '/es/') {
    $_GET['lang'] = 'es';
    require __DIR__ . '/../index.php';
    exit;
}

if ($path === '/sitemap.xml') {
    require __DIR__ . '/../sitemap.php';
    exit;
}

if ($path === '/') {
    require __DIR__ . '/../index.php';
    exit;
}

// Anything that exists on disk is served as-is.
return false;
