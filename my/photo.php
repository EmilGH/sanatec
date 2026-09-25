<?php

declare(strict_types=1);

/** A diver's own dive photo. */

require __DIR__ . '/_init.php';
require_once __DIR__ . '/../src/Uploads.php';

$stmt = db()->prepare('SELECT ph.path FROM dive_photos ph JOIN dives d ON d.id = ph.dive_id WHERE ph.id = :id AND d.customer_id = :c');
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0), ':c' => $customer['id']]);
$path = $stmt->fetchColumn();
if (!$path) {
    http_response_code(404);
    exit;
}
send_upload((string) $path);
