<?php

declare(strict_types=1);

/**
 * SanaTec Diving — public site.
 *
 * Renders the course and cenote catalogue straight from the database in the
 * requested language. There is no build step: what the owner saves in the admin
 * is what the next visitor sees.
 */

define('SANATEC', true);
require __DIR__ . '/src/bootstrap.php';

// Language comes from the rewrite (?lang=es) but is re-derived from the path so
// the site still works if the rewrite rules are ever lost.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$lang = normalize_lang($_GET['lang'] ?? (str_starts_with($path, '/es') ? 'es' : 'en'));

$baseUrl = rtrim((string) cfg('base_url', 'https://sanatecdiving.com'), '/');

$courses = catalog_published('courses');
$routes  = catalog_published('routes');

// Cheap validators so repeat visits and crawlers are not re-rendered needlessly.
$lastModified = catalog_last_modified();
$etag = '"' . md5($lang . '-' . $lastModified . '-' . count($courses) . '-' . count($routes)) . '"';

header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ' . $lang);
header('Cache-Control: public, max-age=300');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<?php require __DIR__ . '/templates/head.php'; ?>
<?php require __DIR__ . '/templates/styles.php'; ?>
</head>
<body>
<?php require __DIR__ . '/templates/public.php'; ?>
</body>
</html>
