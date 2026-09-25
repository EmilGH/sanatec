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

if (preg_match('#^/(es/)?c/([a-z0-9-]+)/?$#', $path, $m)) {
    $_GET['lang'] = $m[1] !== '' ? 'es' : 'en';
    $_GET['share'] = $m[2];
    require __DIR__ . '/../index.php';
    exit;
}
if (preg_match('#^/team/([a-z0-9-]+)/photo\.jpg$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/../team-photo.php';
    exit;
}
if (preg_match('#^/(es/)?team(?:/([a-z0-9-]+))?/?$#', $path, $m)) {
    $_GET['lang'] = ($m[1] ?? '') !== '' ? 'es' : 'en';
    if (($m[2] ?? '') !== '') {
        $_GET['slug'] = $m[2];
    }
    require __DIR__ . '/../team.php';
    exit;
}
if (preg_match('#^/(es/)?passport/([a-f0-9-]+)/?$#', $path, $m)) {
    $_GET['lang'] = ($m[1] ?? '') !== '' ? 'es' : 'en';
    $_GET['id'] = $m[2];
    require __DIR__ . '/../passport.php';
    exit;
}
if (preg_match('#^/og/([a-z0-9-]+)\.png$#', $path, $m)) {
    $_GET['f'] = $m[1];
    require __DIR__ . '/../og.php';
    exit;
}

if ($path === '/privacy' || $path === '/es/privacy') {
    $_GET['lang'] = str_starts_with($path, '/es') ? 'es' : 'en';
    require __DIR__ . '/../privacy.php';
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
