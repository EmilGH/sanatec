<?php

declare(strict_types=1);

/** /og/<name>.png — a link-preview image, rendered on first request and cached. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Og.php';

$name = strtolower(preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['f'] ?? '')) ?? '');
$file = $name !== '' ? og_file($name) : null;

if ($file === null) {
    http_response_code(404);
    exit;
}

send_header('Content-Type: image/png');
send_header('Content-Length: ' . filesize($file));
send_header('Cache-Control: public, max-age=86400');
readfile($file);
