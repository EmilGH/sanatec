<?php

declare(strict_types=1);

/** A photo a diver has marked public on their passport. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Uploads.php';

$stmt = db()->prepare('SELECT ph.path FROM dive_photos ph JOIN dives d ON d.id = ph.dive_id JOIN customers c ON c.id = d.customer_id WHERE ph.id = :id AND ph.is_public = 1 AND c.passport_public = 1');
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$path = $stmt->fetchColumn();
if (!$path) {
    http_response_code(404);
    exit;
}
send_upload((string) $path, '', true);
