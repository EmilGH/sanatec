<?php

declare(strict_types=1);

/**
 * SanaTec Diving — public site.
 *
 * Renders the course and cenote catalogue straight from the database in the
 * requested language. There is no build step: what the owner saves in the admin
 * is what the next visitor sees.
 */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';

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

send_header('Content-Type: text/html; charset=utf-8');
send_header('Content-Language: ' . $lang);
send_header('Cache-Control: public, max-age=300');
send_header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
send_header('ETag: ' . $etag);
send_header('X-Content-Type-Options: nosniff');
send_header('Referrer-Policy: strict-origin-when-cross-origin');
send_header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

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
