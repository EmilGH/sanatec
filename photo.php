<?php

declare(strict_types=1);

/** /photo/<id>.jpg and /photo/<id>-s.jpg — a catalogue photo, public and cached. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Photos.php';

$id = (int) ($_GET['id'] ?? 0);
$thumb = !empty($_GET['s']);
$ph = $id > 0 ? catalog_photo_find($id) : null;
$rel = $ph ? ($thumb ? preg_replace('/\.jpg$/', '-s.jpg', (string) $ph['path']) : (string) $ph['path']) : null;
if (!$rel || upload_path($rel) === null) {
    http_response_code(404);
    exit;
}
send_upload($rel, '', true);
