<?php

declare(strict_types=1);

/** /team/<slug>/photo.jpg — a public team member's photo. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Uploads.php';

$stmt = db()->prepare('SELECT photo_path FROM team_members WHERE public_slug = :s AND profile_public = 1 AND is_active = 1');
$stmt->execute([':s' => preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['slug'] ?? ''))]);
$path = $stmt->fetchColumn();
if (!$path) {
    http_response_code(404);
    exit;
}
send_upload((string) $path, '', true);
